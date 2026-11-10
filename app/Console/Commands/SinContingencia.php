<?php

namespace App\Console\Commands;

use App\Modules\Sin\Services\SinContingenciaService;
use Illuminate\Console\Command;
use Throwable;

class SinContingencia extends Command
{
    protected $signature = 'sin:contingencia {--motivo=2 : Motivo del evento (1 a 4)}';
    protected $description = 'Si hay facturas emitidas fuera de línea y ya hay conexión, registra el evento y las envía al SIN';

    public function handle(SinContingenciaService $servicio): int
    {
        if ($servicio->pendientes()->isEmpty()) {
            return self::SUCCESS; // nada que hacer (silencioso, para usarlo en un cron)
        }

        try {
            $pasos = $servicio->cerrar((int) $this->option('motivo'));
        } catch (Throwable $e) {
            $this->warn($e->getMessage());

            return self::FAILURE;
        }

        $fallo = false;

        foreach ($pasos as $paso) {
            $paso['ok'] ? $this->info($paso['texto']) : $this->error($paso['texto']);
            $fallo = $fallo || ! $paso['ok'];
        }

        return $fallo ? self::FAILURE : self::SUCCESS;
    }
}
