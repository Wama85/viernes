<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinFacturaPrueba;
use App\Modules\Sin\Services\SinFacturaAnulacionService;
use App\Support\ConPuntoVenta;
use Illuminate\Console\Command;
use Throwable;

class SinFacturaRevertir extends Command
{
    use ConPuntoVenta;

    protected $signature = 'sin:factura-revertir
                            {id? : ID de la factura de prueba}
                            {--cantidad=1 : Cuántas anuladas revertir}
                            {--pv= : Punto de venta (por defecto el del .env)}';

    protected $description = 'Revierte la anulación de facturas de prueba, una sola vez por factura (Etapa XI)';

    public function handle(SinFacturaAnulacionService $servicio): int
    {
        $pv = $this->puntoVenta();
        $this->line("Punto de venta: {$pv}");

        $id = $this->argument('id');

        $facturas = $id
            ? SinFacturaPrueba::where('id_sin_factura_prueba', $id)->get()
            : SinFacturaPrueba::where('estado', SinFacturaPrueba::ANULADA)
                ->where('codigo_punto_venta', $pv)
                ->where('reversiones', 0)
                ->orderBy('id_sin_factura_prueba')
                ->limit(max(1, (int) $this->option('cantidad')))
                ->get();

        if ($facturas->isEmpty()) {
            $this->error('No hay facturas anuladas pendientes de reversión.');

            return self::FAILURE;
        }

        $ok = 0;
        $mal = 0;

        foreach ($facturas as $factura) {
            try {
                $factura = $servicio->revertir($factura);
            } catch (Throwable $e) {
                $mal++;
                $this->error("#{$factura->id_sin_factura_prueba}: " . $e->getMessage());

                continue;
            }

            $linea = sprintf(
                '#%d N° %d -> %s (código %s) reversiones: %d | %s',
                $factura->id_sin_factura_prueba,
                $factura->numero,
                $factura->estado,
                $factura->codigo_estado ?? '-',
                $factura->reversiones,
                $factura->mensajes
            );

            if ($factura->estado === SinFacturaPrueba::VALIDADA && $factura->reversiones === 1) {
                $ok++;
                $this->info($linea);
            } else {
                $mal++;
                $this->warn($linea);
            }
        }

        $this->line("Revertidas: {$ok} | Con problemas: {$mal}");

        return $mal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
