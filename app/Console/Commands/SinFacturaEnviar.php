<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinFacturaPrueba;
use App\Modules\Sin\Services\SinFacturaEnvioService;
use App\Modules\Sin\Services\SinFacturaPruebaService;
use App\Support\ConPuntoVenta;
use Illuminate\Console\Command;
use Throwable;

class SinFacturaEnviar extends Command
{
    use ConPuntoVenta;

    protected $signature = 'sin:factura-enviar
                            {id? : ID de la factura de prueba (por defecto, la última sin enviar)}
                            {--nuevas=0 : Genera y envía N facturas nuevas}
                            {--pv= : Punto de venta (por defecto el del .env)}';

    protected $description = 'Envía facturas de prueba al SIN (Etapa IV)';

    public function handle(SinFacturaPruebaService $generador, SinFacturaEnvioService $envio): int
    {
        $pv = $this->puntoVenta();
        $this->line("Punto de venta: {$pv}");

        $facturas = collect();
        $nuevas = (int) $this->option('nuevas');

        if ($nuevas > 0) {
            for ($i = 0; $i < $nuevas; $i++) {
                try {
                    $facturas->push($generador->generar(random_int(1, 3)));
                } catch (Throwable $e) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                }
            }
        } else {
            $id = $this->argument('id');

            $factura = $id
                ? SinFacturaPrueba::find($id)
                : SinFacturaPrueba::where('estado', SinFacturaPrueba::GENERADA)
                    ->where('codigo_punto_venta', $pv)
                    ->orderByDesc('id_sin_factura_prueba')
                    ->first();

            if (! $factura) {
                $this->error('No hay una factura de prueba para enviar.');

                return self::FAILURE;
            }

            $facturas->push($factura);
        }

        $validadas = 0;
        $otras = 0;

        foreach ($facturas as $factura) {
            try {
                $factura = $envio->enviar($factura);
            } catch (Throwable $e) {
                $otras++;
                $this->error("#{$factura->id_sin_factura_prueba}: " . $e->getMessage());

                continue;
            }

            $factura->estado === SinFacturaPrueba::VALIDADA ? $validadas++ : $otras++;

            $linea = sprintf(
                '#%d N° %d -> %s (código %s) recepción: %s | %s',
                $factura->id_sin_factura_prueba,
                $factura->numero,
                $factura->estado,
                $factura->codigo_estado ?? '-',
                $factura->codigo_recepcion ?? '-',
                $factura->mensajes
            );

            $factura->estado === SinFacturaPrueba::VALIDADA ? $this->info($linea) : $this->warn($linea);
        }

        $this->line("Validadas: {$validadas} | Con problemas: {$otras}");

        return $otras > 0 ? self::FAILURE : self::SUCCESS;
    }
}
