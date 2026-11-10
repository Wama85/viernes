<?php

namespace App\Modules\Sin\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * ¿Hay comunicación con el SIN? Se usa para decidir si una factura se emite en línea
 * o fuera de línea (evento significativo). Solo cuenta como «sin conexión» cuando
 * no se puede llegar al servicio (sin internet, tiempo agotado o error 5xx del SIN);
 * un error de token o de datos no es una caída.
 */
class SinComunicacion
{
    private const CLAVE_SIMULAR = 'sin-simular-desconexion';
    private const CLAVE_ESTADO = 'sin-comunicacion-estado';
    private const CLAVE_CAIDA = 'sin-caida-desde';
    private const CLAVE_MOTIVO = 'sin-comunicacion-motivo';

    /** Segundos que se recuerda el último resultado, para no consultar en cada pantalla. */
    private const MEMORIA_SEGUNDOS = 10;

    public function disponible(): bool
    {
        if ($this->simulando()) {
            $this->registrarCaida();

            return false;
        }

        if (! Cache::has(self::CLAVE_ESTADO)) {
            $motivo = null;
            $estado = $this->probar($motivo);

            Cache::put(self::CLAVE_ESTADO, $estado, now()->addSeconds(self::MEMORIA_SEGUNDOS));
            Cache::put(self::CLAVE_MOTIVO, $motivo, now()->addSeconds(self::MEMORIA_SEGUNDOS));

            if (! $estado) {
                Log::warning('SIN: sin comunicación. ' . $motivo);
            }
        }

        $disponible = (bool) Cache::get(self::CLAVE_ESTADO);

        if (! $disponible) {
            $this->registrarCaida();
        }

        return $disponible;
    }

    /** Por qué no hay comunicación (para mostrarlo en pantalla); null si la hay. */
    public function motivo(): ?string
    {
        if ($this->simulando()) {
            return 'Desconexión simulada.';
        }

        return Cache::get(self::CLAVE_MOTIVO);
    }

    /** Desconexión simulada (solo para ensayos en el ambiente de pruebas). */
    public function simulando(): bool
    {
        return (bool) Cache::get(self::CLAVE_SIMULAR, false);
    }

    public function simular(bool $activo): void
    {
        $activo ? Cache::forever(self::CLAVE_SIMULAR, true) : Cache::forget(self::CLAVE_SIMULAR);
        Cache::forget(self::CLAVE_ESTADO);
    }

    /** Momento en que se detectó por primera vez la caída (null si no hay una en curso). */
    public function caidaDesde(): ?\Illuminate\Support\Carbon
    {
        $valor = Cache::get(self::CLAVE_CAIDA);

        return $valor ? \Illuminate\Support\Carbon::parse($valor) : null;
    }

    public function limpiarCaida(): void
    {
        Cache::forget(self::CLAVE_CAIDA);
    }

    private function registrarCaida(): void
    {
        Cache::add(self::CLAVE_CAIDA, now()->toIso8601String(), now()->addDays(7));
    }

    /** @param ?string $motivo se llena con la causa cuando no hay comunicación */
    private function probar(?string &$motivo = null): bool
    {
        $token = (string) config('sin.token');
        $url = config('sin.url_base') . '/ServicioFacturacionCompraVenta?wsdl';

        $contexto = stream_context_create([
            'http' => [
                'header'        => "apikey: TokenApi {$token}\r\n",
                'timeout'       => 6,
                'ignore_errors' => true,
            ],
        ]);

        // 1) Igual que la emisión real: cargar el WSDL con SOAP.
        try {
            new SoapClient($url, [
                'stream_context'     => $contexto,
                'connection_timeout' => 5,
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'exceptions'         => true,
            ]);

            return true;
        } catch (SoapFault $e) {
            $detalle = $e->getMessage();
        } catch (Throwable) {
            // Algo propio del sistema (no de la red): no se toma como caída del SIN.
            return true;
        }

        // 2) No cargó: se distingue «no hay red» de «el SIN respondió con un error».
        if (! ini_get('allow_url_fopen')) {
            $motivo = 'No se pudo cargar el servicio del SIN: ' . $detalle;

            return false;
        }

        @file_get_contents($url, false, $contexto);
        $estado = $this->estadoHttp($http_response_header ?? []);

        if ($estado !== null && $estado < 500) {
            // El SIN contestó (por ejemplo 401 por el token): hay comunicación; el error real se mostrará al enviar.
            return true;
        }

        $motivo = $estado !== null
            ? "El SIN respondió con error HTTP {$estado}."
            : 'No hay respuesta del SIN: ' . $detalle;

        return false;
    }

    /** Código HTTP de la última respuesta (tras redirecciones), o null si no hubo respuesta. */
    private function estadoHttp(array $cabeceras): ?int
    {
        $codigo = null;

        foreach ($cabeceras as $linea) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $linea, $m)) {
                $codigo = (int) $m[1];
            }
        }

        return $codigo;
    }
}
