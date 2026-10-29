<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinCuis as Cuis;
use App\Modules\Sin\Services\SinCliente;
use App\Support\ConPuntoVenta;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class SinCuis extends Command
{
    use ConPuntoVenta;

    protected $signature = 'sin:cuis {--pv= : Punto de venta (por defecto el del .env)}';
    protected $description = 'Solicita el CUIS al SIN y lo guarda (Etapa I)';

    public function handle(SinCliente $sin): int
    {
        $pv = $this->puntoVenta();
        $this->line("Punto de venta: {$pv}");

        try {
            $respuesta = $sin->servicio('FacturacionCodigos')
                ->cuis(['SolicitudCuis' => $sin->solicitudBase()]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // La respuesta viene envuelta en un objeto cuyo nombre puede variar
        $arreglo = (array) $respuesta;
        $datos = reset($arreglo);

        $this->line(json_encode($respuesta, JSON_UNESCAPED_UNICODE));

        // Código 980: ya existía un CUIS vigente; el SIN lo devuelve igual y es válido.
        $yaVigente = (int) ($datos->mensajesList->codigo ?? 0) === 980;

        if (empty($datos->codigo) || empty($datos->fechaVigencia)
            || (! ($datos->transaccion ?? false) && ! $yaVigente)) {
            $this->error('El SIN no entregó un CUIS. Revisa el mensaje de arriba.');

            return self::FAILURE;
        }

        $vigencia = Carbon::parse($datos->fechaVigencia)->setTimezone(config('app.timezone'));

        $guardado = Cuis::updateOrCreate(
            ['cuis' => $datos->codigo],
            [
                'codigo_ambiente'    => config('sin.ambiente'),
                'codigo_sucursal'    => config('sin.sucursal'),
                'codigo_punto_venta' => config('sin.punto_venta'),
                'fecha_vigencia'     => $vigencia,
                'vigente'            => true,
            ]
        );

        // Solo el CUIS que el SIN acaba de confirmar queda vigente para este punto de venta.
        Cuis::where('codigo_ambiente', config('sin.ambiente'))
            ->where('codigo_sucursal', config('sin.sucursal'))
            ->where('codigo_punto_venta', config('sin.punto_venta'))
            ->where('id_sin_cuis', '!=', $guardado->id_sin_cuis)
            ->update(['vigente' => false]);

        $this->info(
            ($yaVigente ? 'Ya existía un CUIS vigente; se conserva. ' : 'CUIS nuevo guardado. ')
            . 'Vigente hasta ' . $vigencia->format('d/m/Y H:i') . '.'
        );

        return self::SUCCESS;
    }
}
