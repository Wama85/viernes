<?php

namespace App\Modules\Usuarios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    /** Envía al usuario a la pantalla de Google (siempre pregunta qué cuenta usar). */
    public function redirect()
    {
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /** Google devuelve aquí. Solo entra quien ya está registrado con ese correo y está activo. */
    public function callback(Request $request)
    {
        // El usuario canceló o rechazó en la pantalla de Google.
        if ($request->has('error')) {
            return $this->rechazar('Se canceló el inicio de sesión con Google.');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google login: ' . $e->getMessage());

            return $this->rechazar('No se pudo iniciar sesión con Google. Inténtalo de nuevo.');
        }

        $correo = mb_strtolower(trim((string) $google->getEmail()));

        if ($correo === '') {
            return $this->rechazar('Google no entregó un correo electrónico.');
        }

        // Google debe haber verificado ese correo.
        $datos = (array) ($google->user ?? []);
        $verificado = $datos['email_verified'] ?? $datos['verified_email'] ?? true;

        if (! filter_var($verificado, FILTER_VALIDATE_BOOLEAN)) {
            return $this->rechazar('Tu correo de Google no está verificado.');
        }

        $usuario = Usuario::whereRaw('LOWER(email) = ?', [$correo])->first();

        if (! $usuario) {
            Log::notice("Google login rechazado: correo no registrado ({$correo}).");

            return $this->rechazar('Tu correo no está registrado en el sistema. Pide al administrador que te registre con tu correo de Gmail.');
        }

        // Baja lógica: un usuario desactivado no puede entrar.
        if (! $this->estaActivo($usuario)) {
            return $this->rechazar('Tu usuario está desactivado. Contacta al administrador.');
        }

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function estaActivo(Usuario $usuario): bool
    {
        return in_array($usuario->usuario_estado, [1, '1', true, 'ACTIVO', 'activo'], true);
    }

    private function rechazar(string $mensaje)
    {
        return redirect()->route('login')->withErrors(['email' => $mensaje]);
    }
}
