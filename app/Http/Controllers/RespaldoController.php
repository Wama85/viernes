<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RespaldoController extends Controller
{
    /** Solo nombres de respaldo válidos: evita rutas como ../../.env */
    private const PATRON = '/^[A-Za-z0-9_\-]+\.(zip|sql\.gz)$/';

    private const DIAS_RECORDATORIO = 7;

    /** Pantalla con los respaldos guardados. */
    public function index()
    {
        $this->soloAdmin();

        $carpeta = $this->carpeta();

        $archivos = collect(array_merge(
            glob($carpeta . DIRECTORY_SEPARATOR . '*.zip') ?: [],
            glob($carpeta . DIRECTORY_SEPARATOR . '*.sql.gz') ?: []
        ))
            ->filter(fn ($ruta) => preg_match(self::PATRON, basename($ruta)) === 1)
            ->sortByDesc(fn ($ruta) => basename($ruta))
            ->values()
            ->map(fn ($ruta) => [
                'nombre'    => basename($ruta),
                'fecha'     => Carbon::createFromTimestamp(filemtime($ruta), config('app.timezone')),
                'mb'        => filesize($ruta) / 1048576,
                'protegido' => str_ends_with($ruta, '.zip'),
            ]);

        $dias = $this->diasDesdeUltimaDescarga();

        return view('respaldos.index', [
            'archivos'  => $archivos,
            'dias'      => $dias,
            'alDia'     => $dias !== null && $dias < self::DIAS_RECORDATORIO,
            'conservar' => (int) config('respaldo.conservar'),
        ]);
    }

    /** Crea un respaldo ahora (sin enviar correo). */
    /** Crea un respaldo y lo entrega para descarga en el mismo paso. */
    public function crear()
    {
        $this->soloAdmin();

        set_time_limit(300);

        try {
            $codigo = Artisan::call('backup:correo', ['--sin-correo' => true]);
        } catch (Throwable $e) {
            Log::error('Respaldo manual: ' . $e->getMessage());
            $codigo = 1;
        }

        $nuevo = $codigo === 0 ? $this->masReciente() : null;

        if (! $nuevo) {
            return response()->json(
                ['mensaje' => 'No se pudo crear la copia. Revisa storage/logs/laravel.log.'],
                500
            );
        }

        return $this->entregar($nuevo);
    }

    /** Descarga desde la pantalla de respaldos (usuario con sesión iniciada). */
    public function bajar(string $archivo)
    {
        $this->soloAdmin();

        return $this->entregar($archivo);
    }

    /** Descarga desde el enlace del correo: enlace firmado vigente y sesión de administrador. */
    public function descargar(string $archivo)
    {
        $this->soloAdmin();

        return $this->entregar($archivo);
    }

    // ------------------------------------------------------------------

    private function soloAdmin(): void
    {
        abort_unless(Auth::user()->id_rol == 1, 403, 'Solo un administrador puede gestionar respaldos.');
    }

    private function carpeta(): string
    {
        return rtrim((string) config('respaldo.carpeta'), '/\\');
    }

    private function entregar(string $archivo)
    {
        abort_unless(preg_match(self::PATRON, $archivo) === 1, 404);

        $ruta = $this->carpeta() . DIRECTORY_SEPARATOR . $archivo;
        abort_unless(is_file($ruta), 404, 'Este respaldo ya no está disponible.');

        $this->registrarDescarga($archivo);
        Log::info("Respaldo descargado: {$archivo} por el usuario " . Auth::id());

        $tipo = str_ends_with($archivo, '.zip') ? 'application/zip' : 'application/gzip';

        return response()->download($ruta, $archivo, ['Content-Type' => $tipo]);
    }

    /** Anota la descarga: es lo que apaga el recordatorio durante 7 días. */
    private function registrarDescarga(string $archivo): void
    {
        try {
            DB::table('respaldo_descargas')->insert([
                'id_usuario' => Auth::id(),
                'archivo'    => $archivo,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar la descarga del respaldo: ' . $e->getMessage());
        }
    }

    /** Días enteros desde la última descarga; null si nunca se descargó (o no hay tabla). */
    private function diasDesdeUltimaDescarga(): ?int
    {
        try {
            $ultima = DB::table('respaldo_descargas')->max('created_at');
        } catch (Throwable $e) {
            return null;
        }

        if (! $ultima) {
            return null;
        }

        return intdiv(max(0, now()->timestamp - Carbon::parse($ultima)->timestamp), 86400);
    }
        /** Nombre del respaldo más reciente de la carpeta. */
    private function masReciente(): ?string
    {
        $carpeta = $this->carpeta();

        $nombres = array_filter(
            array_map('basename', array_merge(
                glob($carpeta . DIRECTORY_SEPARATOR . '*.zip') ?: [],
                glob($carpeta . DIRECTORY_SEPARATOR . '*.sql.gz') ?: []
            )),
            fn ($nombre) => preg_match(self::PATRON, $nombre) === 1
        );

        rsort($nombres); // el nombre lleva la fecha y hora

        return $nombres[0] ?? null;
    }
}
