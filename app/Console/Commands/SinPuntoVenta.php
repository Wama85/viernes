<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinCuis;
use App\Modules\Sin\Services\SinCliente;
use Illuminate\Console\Command;
use Throwable;

class SinPuntoVenta extends Command
{
    protected $signature = 'sin:punto-venta
                            {--tipo=5 : Tipo de punto de venta (catálogo ParametricaTipoPuntoVenta)}
                            {--nombre=PUNTO DE VENTA 1 : Nombre del punto de venta}
                            {--descripcion=PUNTO DE VENTA PARA PRUEBAS : Descripción}';

    protected $description = 'Registra un punto de venta en el SIN (necesario para las pruebas con punto de venta 1)';

    public function handle(SinCliente $sin): int
    {
        // El registro se hace con el CUIS del punto de venta 0
        $cuis = SinCuis::actual();

        if (! $cuis) {
            $this->error('No hay un CUIS vigente. Ejecuta primero: php artisan sin:cuis');

            return self::FAILURE;
        }

        $solicitud = [
            'codigoAmbiente'       => config('sin.ambiente'),
            'codigoModalidad'      => config('sin.modalidad'),
            'codigoSistema'        => config('sin.codigo_sistema'),
            'codigoSucursal'       => config('sin.sucursal'),
            'codigoTipoPuntoVenta' => (int) $this->option('tipo'),
            'cuis'                 => $cuis->cuis,
            'descripcion'          => $this->option('descripcion'),
            'nit'                  => (int) config('sin.nit'),
            'nombrePuntoVenta'     => $this->option('nombre'),
        ];

        try {
            $respuesta = $sin->servicio('FacturacionOperaciones')
                ->registroPuntoVenta(['SolicitudRegistroPuntoVenta' => $solicitud]);

            $arreglo = (array) $respuesta;
            $datos = reset($arreglo);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! ($datos->transaccion ?? false)) {
            $this->warn('El SIN no registró el punto de venta: ' . json_encode($datos->mensajesList ?? $datos, JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }

        $this->info('Punto de venta registrado. Código asignado por el SIN: ' . ($datos->codigoPuntoVenta ?? '?'));

        return self::SUCCESS;
    }
}
