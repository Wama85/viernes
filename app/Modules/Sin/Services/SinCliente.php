<?php

namespace App\Modules\Sin\Services;

use RuntimeException;
use SoapClient;

class SinCliente
{
    private array $clientes = [];

    /** Cliente SOAP de uno de los servicios del SIN (el token va en la cabecera apikey). */
    public function servicio(string $nombre): SoapClient
    {
        if (! isset($this->clientes[$nombre])) {
            $token = config('sin.token');

            if (! $token) {
                throw new RuntimeException('Falta SIN_TOKEN en el archivo .env');
            }

            $contexto = stream_context_create([
                'http' => [
                    'header'  => "apikey: TokenApi {$token}\r\n",
                    'timeout' => 30,
                ],
            ]);

            $this->clientes[$nombre] = new SoapClient(config('sin.url_base') . "/{$nombre}?wsdl", [
                'stream_context'     => $contexto,
                'trace'              => true,
                'exceptions'         => true,
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'connection_timeout' => 30,
            ]);
        }

        return $this->clientes[$nombre];
    }

    /** Datos que casi todas las solicitudes del SIN llevan. */
    public function solicitudBase(): array
    {
        return [
            'codigoAmbiente'   => config('sin.ambiente'),
            'codigoModalidad'  => config('sin.modalidad'),
            'codigoPuntoVenta' => config('sin.punto_venta'),
            'codigoSistema'    => config('sin.codigo_sistema'),
            'codigoSucursal'   => config('sin.sucursal'),
            'nit'              => (int) config('sin.nit'),
        ];
    }
}
