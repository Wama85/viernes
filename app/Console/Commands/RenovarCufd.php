<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RenovarCufd extends Command
{
    protected $signature = 'sin:cufd-diario
                            {--pv=0,1 : Puntos de venta a renovar, separados por coma}
                            {--intentos=3 : Intentos por punto de venta}';

    protected $description = 'Renueva el CUFD de cada punto de venta (pensado para el cron diario)';

    public function handle(): int
    {
        $puntos = array_values(array_filter(
            array_map('trim', explode(',', (string) $this->option('pv'))),
            fn ($p) => $p !== ''
        ));
        $intentos = max(1, (int) $this->option('intentos'));
        $fallidos = [];

        foreach ($puntos as $pv) {
            if (! ctype_digit($pv)) {
                $this->error("Punto de venta no válido: {$pv}");
                $fallidos[] = $pv;

                continue;
            }

            if (! $this->renovar((int) $pv, $intentos)) {
                $fallidos[] = $pv;
            }
        }

        if ($fallidos) {
            $lista = implode(', ', $fallidos);
            Log::error("SIN: no se pudo renovar el CUFD del punto de venta {$lista}");
            $this->avisarFallo($lista);

            return self::FAILURE;
        }

        Log::info('SIN: CUFD renovado para los puntos de venta ' . implode(', ', $puntos));

        return self::SUCCESS;
    }

    private function renovar(int $pv, int $intentos): bool
    {
        for ($i = 1; $i <= $intentos; $i++) {
            try {
                $codigo = Artisan::call('sin:cufd', ['--pv' => $pv]);
                $salida = trim(Artisan::output());

                if ($codigo === 0) {
                    $this->info("Punto de venta {$pv}: CUFD renovado.");

                    return true;
                }

                $this->warn("Punto de venta {$pv}, intento {$i}/{$intentos}: {$salida}");
                Log::warning("SIN CUFD pv {$pv}, intento {$i}/{$intentos}: {$salida}");
            } catch (Throwable $e) {
                $this->warn("Punto de venta {$pv}, intento {$i}/{$intentos}: " . $e->getMessage());
                Log::warning("SIN CUFD pv {$pv}, intento {$i}/{$intentos}: " . $e->getMessage());
            }

            if ($i < $intentos) {
                sleep(10);
            }
        }

        return false;
    }

    /** Correo al propietario: sin CUFD vigente no se pueden emitir facturas. */
    private function avisarFallo(string $puntos): void
    {
        $destinatarios = array_values(array_filter(array_map('trim', explode(',', (string) config('respaldo.correo')))));
        if (! $destinatarios) {
            return;
        }

        try {
            $texto = "No se pudo renovar el CUFD del SIN (punto de venta: {$puntos}).\n\n"
                . "Mientras no haya un CUFD vigente, no se podrán emitir facturas.\n"
                . "Revisa storage/logs/laravel.log o renuévalo a mano con:\n"
                . "php artisan sin:cufd --pv=NUMERO\n";

            Mail::raw($texto, function ($m) use ($destinatarios) {
                $m->to($destinatarios)->subject(config('app.name') . ' · Falló la renovación del CUFD');
            });
        } catch (Throwable $e) {
            Log::warning('SIN: no se pudo enviar el aviso de CUFD: ' . $e->getMessage());
        }
    }
}
