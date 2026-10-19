<?php

namespace App\Modules\Usuarios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class RecuperacionController extends Controller
{
    private const TABLA = 'codigos_recuperacion';
    private const VIGENCIA_MINUTOS = 10;
    private const MAX_INTENTOS = 5;
    private const ESPERA_SEGUNDOS = 60;

    // ---------------------------------------------------------------- Paso 1: pedir el código

    public function solicitar()
    {
        return view('auth.forgot-password');
    }

    public function enviar(Request $request)
    {
        $datos = $request->validate(
            ['email' => ['required', 'string', 'email', 'max:100']],
            ['email.required' => 'Ingresa tu correo.', 'email.email' => 'El correo no tiene un formato válido.']
        );

        $correo = mb_strtolower(trim($datos['email']));
        $request->session()->put('recuperacion_email', $correo);
        $request->session()->forget('recuperacion_ok');

        if ($espera = $this->segundosDeEspera($request)) {
            return redirect()->route('password.codigo')
                ->with('status', "Ya te enviamos un código. Espera {$espera} segundos para pedir otro.");
        }

        $this->generarYEnviar($correo);
        $request->session()->put('recuperacion_envio_en', now()->timestamp);

        return redirect()->route('password.codigo')->with('status', $this->mensajeEnvio());
    }

    public function reenviar(Request $request)
    {
        $correo = $request->session()->get('recuperacion_email');

        if (! $correo) {
            return redirect()->route('password.request');
        }

        if ($espera = $this->segundosDeEspera($request)) {
            return back()->withErrors(['codigo' => "Espera {$espera} segundos para pedir otro código."]);
        }

        $this->generarYEnviar($correo);
        $request->session()->put('recuperacion_envio_en', now()->timestamp);

        return back()->with('status', $this->mensajeEnvio());
    }

    // ---------------------------------------------------------------- Paso 2: ingresar el código

    public function codigo(Request $request)
    {
        $correo = $request->session()->get('recuperacion_email');

        if (! $correo) {
            return redirect()->route('password.request');
        }

        return view('auth.codigo-recuperacion', ['correoOculto' => $this->ocultar($correo)]);
    }

    public function verificar(Request $request)
    {
        $request->validate(
            ['codigo' => ['required', 'digits:6']],
            ['codigo.required' => 'Ingresa el código.', 'codigo.digits' => 'El código tiene 6 dígitos.']
        );

        $correo = $request->session()->get('recuperacion_email');

        if (! $correo) {
            return redirect()->route('password.request');
        }

        $fila = DB::table(self::TABLA)->where('email', $correo)->first();

        if (! $fila || now()->gt(Carbon::parse($fila->expira_en))) {
            return back()->withErrors(['codigo' => 'El código venció o no es válido. Solicita uno nuevo.']);
        }

        if ($fila->intentos >= self::MAX_INTENTOS) {
            DB::table(self::TABLA)->where('id', $fila->id)->delete();

            return back()->withErrors(['codigo' => 'Superaste los intentos permitidos. Solicita un código nuevo.']);
        }

        if (! Hash::check($request->input('codigo'), $fila->codigo_hash)) {
            DB::table(self::TABLA)->where('id', $fila->id)->increment('intentos');
            $restantes = self::MAX_INTENTOS - ($fila->intentos + 1);

            return back()->withErrors([
                'codigo' => $restantes > 0
                    ? "Código incorrecto. Te quedan {$restantes} intento(s)."
                    : 'Código incorrecto. Solicita un código nuevo.',
            ]);
        }

        // Código correcto: se habilita el cambio de contraseña por unos minutos.
        DB::table(self::TABLA)->where('id', $fila->id)->update([
            'verificado_en' => now(),
            'expira_en'     => now()->addMinutes(self::VIGENCIA_MINUTOS),
            'updated_at'    => now(),
        ]);

        $request->session()->put('recuperacion_ok', true);

        return redirect()->route('password.nueva');
    }

    // ---------------------------------------------------------------- Paso 3: nueva contraseña

    public function nueva(Request $request)
    {
        if (! $this->verificado($request)) {
            return redirect()->route('password.request');
        }

        return view('auth.nueva-password');
    }

    public function actualizar(Request $request)
    {
        if (! $this->verificado($request)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'La verificación venció. Solicita un código nuevo.']);
        }

        $letrasYNumeros = function ($atributo, $valor, $fallar) {
            if (! preg_match('/\p{L}/u', (string) $valor) || ! preg_match('/\d/', (string) $valor)) {
                $fallar('La contraseña debe incluir al menos una letra y un número.');
            }
        };

        $request->validate([
            'password'              => ['required', 'string', 'min:8', 'max:72', $letrasYNumeros],
            'password_confirmation' => ['required', 'same:password'],
        ], [
            'password.required'              => 'La contraseña es obligatoria.',
            'password.min'                   => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max'                   => 'La contraseña admite como máximo 72 caracteres.',
            'password_confirmation.required' => 'Confirma la contraseña.',
            'password_confirmation.same'     => 'Las contraseñas no coinciden.',
        ]);

        $correo = $request->session()->get('recuperacion_email');
        $usuario = Usuario::whereRaw('LOWER(email) = ?', [$correo])->first();

        if (! $usuario || ! $this->activo($usuario)) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'No se pudo cambiar la contraseña. Contacta al administrador.']);
        }

        $usuario->forceFill([
            'password'       => Hash::make($request->input('password')),
            'remember_token' => Str::random(60),
        ])->saveQuietly();

        DB::table(self::TABLA)->where('email', $correo)->delete();
        $request->session()->forget(['recuperacion_email', 'recuperacion_ok', 'recuperacion_envio_en']);

        return redirect()->route('login')->with('status', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }

    // ---------------------------------------------------------------- Apoyo

    /** Crea el código y lo envía. Si el correo no existe o está de baja, no hace nada (sin avisar). */
    private function generarYEnviar(string $correo): void
    {
        $usuario = Usuario::whereRaw('LOWER(email) = ?', [$correo])->first();

        if (! $usuario || ! $this->activo($usuario)) {
            return;
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table(self::TABLA)->where('email', $correo)->delete();
        DB::table(self::TABLA)->insert([
            'email'       => $correo,
            'codigo_hash' => Hash::make($codigo),
            'intentos'    => 0,
            'expira_en'   => now()->addMinutes(self::VIGENCIA_MINUTOS),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $minutos = self::VIGENCIA_MINUTOS;
        $sistema = e(config('app.name'));
        $nombre = e($usuario->nombre);

        $html = "<div style=\"font-family:Arial,sans-serif;max-width:480px;margin:auto;color:#222;\">"
            . "<h2 style=\"color:#0c3082;\">Recuperar contraseña</h2>"
            . "<p>Hola {$nombre},</p>"
            . "<p>Tu código de verificación es:</p>"
            . "<p style=\"font-size:32px;font-weight:bold;letter-spacing:8px;color:#0c3082;\">{$codigo}</p>"
            . "<p>Vale por <strong>{$minutos} minutos</strong> y solo se puede usar una vez.</p>"
            . "<p style=\"font-size:12px;color:#666;\">Si no lo pediste tú, ignora este correo: tu contraseña no cambia.<br>{$sistema}</p>"
            . "</div>";

        try {
            Mail::html($html, fn ($m) => $m->to($correo)->subject('Código para recuperar tu contraseña'));
        } catch (Throwable $e) {
            Log::error('Recuperación: no se pudo enviar el código: ' . $e->getMessage());
        }
    }

    private function mensajeEnvio(): string
    {
        return 'Si el correo está registrado, te enviamos un código de 6 dígitos. Revisa también la carpeta de spam.';
    }

    private function segundosDeEspera(Request $request): int
    {
        $ultimo = (int) $request->session()->get('recuperacion_envio_en', 0);
        $faltan = ($ultimo + self::ESPERA_SEGUNDOS) - now()->timestamp;

        return max(0, $faltan);
    }

    /** ¿Ya ingresó un código correcto y sigue vigente? */
    private function verificado(Request $request): bool
    {
        $correo = $request->session()->get('recuperacion_email');

        if (! $correo || ! $request->session()->get('recuperacion_ok')) {
            return false;
        }

        $fila = DB::table(self::TABLA)->where('email', $correo)->first();

        return $fila && $fila->verificado_en && now()->lte(Carbon::parse($fila->expira_en));
    }

    private function activo(Usuario $usuario): bool
    {
        return in_array($usuario->usuario_estado, [1, '1', true, 'ACTIVO', 'activo'], true);
    }

    /** wi***@gmail.com */
    private function ocultar(string $correo): string
    {
        [$local, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        return mb_substr($local, 0, 2) . str_repeat('*', max(3, mb_strlen($local) - 2)) . '@' . $dominio;
    }
}
