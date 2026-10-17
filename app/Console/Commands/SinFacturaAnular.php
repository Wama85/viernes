<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinFacturaPrueba;
use App\Modules\Sin\Services\SinFacturaAnulacionService;
use App\Support\ConPuntoVenta;
use Illuminate\Console\Command;
use Throwable;

class SinFacturaAnular extends Command
{
    use ConPuntoVenta;

    protected $signature = 'sin:factura-anular
                            {id? : ID de la factura de prueba}
                            {--cantidad=1 : Cuántas facturas validadas anular}
                            {--motivo=0 : Motivo 1, 3 o 4 (0 = al azar)}
                            {--pv= : Punto de venta (por defecto el del .env)}';

    protected $description = 'Anula facturas de prueba validadas (Etapa VII)';

    public function handle(SinFacturaAnulacionService $servicio): int
    {
        $pv = $this->puntoVenta();
        $this->line("Punto de venta: {$pv}");

        $id = $this->argument('id');

        $facturas = $id
            ? SinFacturaPrueba::where('id_sin_factura_prueba', $id)->get()
            : SinFacturaPrueba::where('estado', SinFacturaPrueba::VALIDADA)
                ->where('codigo_punto_venta', $pv)
                ->where('reversiones', 0)
                ->orderBy('id_sin_factura_prueba')
                ->limit(max(1, (int) $this->option('cantidad')))
                ->get();

        if ($facturas->isEmpty()) {
            $this->error('No hay facturas validadas para anular.');

            return self::FAILURE;
        }

        $ok = 0;
        $mal = 0;

        foreach ($facturas as $factura) {
            $motivo = (int) $this->option('motivo') ?: SinFacturaAnulacionService::MOTIVOS[array_rand(SinFacturaAnulacionService::MOTIVOS)];

            try {
                $factura = $servicio->anular($factura, $motivo);
            } catch (Throwable $e) {
                $mal++;
                $this->error("#{$factura->id_sin_factura_prueba}: " . $e->getMessage());

                continue;
            }

            $linea = sprintf(
                '#%d N° %d -> %s (código %s) motivo %d | %s',
                $factura->id_sin_factura_prueba,
                $factura->numero,
                $factura->estado,
                $factura->codigo_estado ?? '-',
                $motivo,
                $factura->mensajes
            );

            if ($factura->estado === SinFacturaPrueba::ANULADA) {
                $ok++;
                $this->info($linea);
            } else {
                $mal++;
                $this->warn($linea);
            }
        }

        $this->line("Anuladas: {$ok} | Con problemas: {$mal}");

        return $mal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
