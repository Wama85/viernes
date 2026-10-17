<?php

namespace App\Console\Commands;

use App\Modules\Sin\Services\SinCliente;
use Illuminate\Console\Command;
use Throwable;

class SinComunicacion extends Command
{
    protected $signature = 'sin:comunicacion';
    protected $description = 'Comprueba la conexión con los servicios del SIN (ambiente de pruebas)';

    public function handle(SinCliente $sin): int
    {
        $servicios = [
            'FacturacionCodigos',
            'FacturacionSincronizacion',
            'FacturacionOperaciones',
            'ServicioFacturacionCompraVenta',
        ];

        $fallo = false;

        foreach ($servicios as $servicio) {
            try {
                $respuesta = $sin->servicio($servicio)->verificarComunicacion();
                $this->info("{$servicio}: " . json_encode($respuesta, JSON_UNESCAPED_UNICODE));
            } catch (Throwable $e) {
                $fallo = true;
                $this->error("{$servicio}: " . $e->getMessage());
            }
        }

        return $fallo ? self::FAILURE : self::SUCCESS;
    }
}
