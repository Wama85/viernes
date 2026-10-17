<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinPaquetePrueba;
use App\Modules\Sin\Services\SinPaqueteService;
use App\Support\ConPuntoVenta;
use Illuminate\Console\Command;
use Throwable;

class SinPaqueteValidar extends Command
{
    use ConPuntoVenta;

    protected $signature = 'sin:paquete-validar
                            {--cantidad=0 : Cuántos paquetes validar (0 = todos los pendientes)}
                            {--pv= : Punto de venta (por defecto el del .env)}';

    protected $description = 'Valida la recepción de los paquetes enviados al SIN (Etapa VI)';

    public function handle(SinPaqueteService $servicio): int
    {
        $pv = $this->puntoVenta();
        $this->line("Punto de venta: {$pv}");

        $consulta = SinPaquetePrueba::where('codigo_punto_venta', $pv)
            ->where('estado', SinPaquetePrueba::ENVIADO)
            ->orderBy('id_sin_paquete_prueba');

        if ((int) $this->option('cantidad') > 0) {
            $consulta->limit((int) $this->option('cantidad'));
        }

        $paquetes = $consulta->get();

        if ($paquetes->isEmpty()) {
            $this->warn('No hay paquetes pendientes de validar.');

            return self::SUCCESS;
        }

        $validados = 0;
        $otros = 0;

        foreach ($paquetes as $paquete) {
            try {
                $paquete = $servicio->validar($paquete);
            } catch (Throwable $e) {
                $otros++;
                $this->error("Paquete #{$paquete->id_sin_paquete_prueba}: " . $e->getMessage());

                continue;
            }

            $linea = sprintf(
                'Paquete #%d (motivo %d) -> %s (código %s) | %s',
                $paquete->id_sin_paquete_prueba,
                $paquete->codigo_motivo,
                $paquete->estado,
                $paquete->codigo_estado_validacion ?? '-',
                $paquete->mensajes
            );

            if ($paquete->estado === SinPaquetePrueba::VALIDADO) {
                $validados++;
                $this->info($linea);
            } else {
                $otros++;
                $this->warn($linea);
            }
        }

        $this->line("Validados: {$validados} | Pendientes o con problemas: {$otros}");

        return self::SUCCESS;
    }
}
