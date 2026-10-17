<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinCufd as Cufd;
use App\Modules\Sin\Models\SinCuis;
use App\Modules\Sin\Services\SinCliente;
use App\Support\ConPuntoVenta;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class SinCufd extends Command
{
    use ConPuntoVenta;

    protected $signature = 'sin:cufd
                            {--veces=1 : Cuántas veces solicitar el CUFD}
                            {--pv= : Punto de venta (por defecto el del .env)}';

    protected $description = 'Solicita el CUFD al SIN y guarda el más reciente (Etapa III)';

    public function handle(SinCliente $sin): int
    {
        $pv = $this->puntoVenta();
        $this->line("Punto de venta: {$pv}");

        $cuis = SinCuis::actual();

        if (! $cuis) {
            $this->error("No hay un CUIS vigente para el punto de venta {$pv}. Ejecuta primero: php artisan sin:cuis --pv={$pv}");

            return self::FAILURE;
        }

        $solicitud = $sin->solicitudBase();
        $solicitud['cuis'] = $cuis->cuis;

        $servicio = $sin->servicio('FacturacionCodigos');
        $veces = max(1, (int) $this->option('veces'));
        $correctos = 0;
        $fallidos = 0;
        $ultimo = null;

        for ($i = 1; $i <= $veces; $i++) {
            try {
                $respuesta = $servicio->cufd(['SolicitudCufd' => $solicitud]);
                $arreglo = (array) $respuesta;
                $datos = reset($arreglo);
            } catch (Throwable $e) {
                $fallidos++;

                if ($i === 1) {
                    $this->error($e->getMessage());
                }

                continue;
            }

            $ok = ($datos->transaccion ?? false) && ! empty($datos->codigo);

            if (! $ok) {
                $fallidos++;

                if ($i === 1) {
                    $this->warn('El SIN no entregó el CUFD: ' . json_encode($datos->mensajesList ?? $datos, JSON_UNESCAPED_UNICODE));
                }

                continue;
            }

            $correctos++;
            $ultimo = $datos; // el SIN considera vigente el más reciente
        }

        // Se guarda el CUFD más reciente; los anteriores de este punto de venta dejan de ser vigentes
        if ($ultimo) {
            $vigencia = Carbon::parse($ultimo->fechaVigencia)->setTimezone(config('app.timezone'));

            Cufd::where('codigo_ambiente', config('sin.ambiente'))
                ->where('codigo_sucursal', config('sin.sucursal'))
                ->where('codigo_punto_venta', config('sin.punto_venta'))
                ->update(['vigente' => false]);

            Cufd::updateOrCreate(
                ['cufd' => $ultimo->codigo],
                [
                    'codigo_ambiente'    => config('sin.ambiente'),
                    'codigo_sucursal'    => config('sin.sucursal'),
                    'codigo_punto_venta' => config('sin.punto_venta'),
                    'codigo_control'     => $ultimo->codigoControl,
                    'direccion'          => $ultimo->direccion ?? null,
                    'fecha_vigencia'     => $vigencia,
                    'vigente'            => true,
                ]
            );

            $this->info('CUFD guardado. Vigente hasta ' . $vigencia->format('d/m/Y H:i') . '.');
        }

        $this->line("Solicitudes: {$veces} | Correctas: {$correctos} | Fallidas: {$fallidos}");

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
