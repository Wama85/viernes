<?php

namespace App\Modules\Sin\Services;

use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Models\SinCuis;
use RuntimeException;
use Throwable;

/** Sincroniza con el SIN todos los catálogos y los guarda (lo mismo que `php artisan sin:sincronizar`). */
class SinSincronizacionService
{
    public const METODOS = [
        'sincronizarActividades',
        'sincronizarFechaHora',
        'sincronizarListaActividadesDocumentoSector',
        'sincronizarListaLeyendasFactura',
        'sincronizarListaMensajesServicios',
        'sincronizarListaProductosServicios',
        'sincronizarParametricaEventosSignificativos',
        'sincronizarParametricaMotivoAnulacion',
        'sincronizarParametricaPaisOrigen',
        'sincronizarParametricaTipoDocumentoIdentidad',
        'sincronizarParametricaTipoDocumentoSector',
        'sincronizarParametricaTipoEmision',
        'sincronizarParametricaTipoHabitacion',
        'sincronizarParametricaTipoMetodoPago',
        'sincronizarParametricaTipoMoneda',
        'sincronizarParametricaTipoPuntoVenta',
        'sincronizarParametricaTiposFactura',
        'sincronizarParametricaUnidadMedida',
    ];

    public function __construct(private SinCliente $sin)
    {
    }

    /** Nombre con el que se guarda el catálogo: «sincronizarActividades» pasa a «Actividades». */
    public static function nombre(string $metodo): string
    {
        return substr($metodo, strlen('sincronizar'));
    }

    /** Cantidad de registros de un catálogo guardado (la lista que trae la respuesta del SIN). */
    public static function contar(?array $datos): ?int
    {
        foreach ((array) $datos as $clave => $valor) {
            if (is_string($clave) && str_starts_with($clave, 'lista') && is_array($valor)) {
                // Con un solo registro, el SOAP lo entrega como objeto y no como lista.
                return array_is_list($valor) ? count($valor) : 1;
            }
        }

        return null;
    }

    /**
     * Sincroniza los 18 catálogos. Devuelve el resultado de cada uno:
     * [catalogo => ['ok' => bool, 'mensaje' => string]].
     *
     * @return array<string, array{ok: bool, mensaje: string}>
     */
    public function sincronizar(): array
    {
        $cuis = SinCuis::actual();

        if (! $cuis) {
            throw new RuntimeException('No hay un CUIS vigente. Solicítalo primero con: php artisan sin:cuis');
        }

        $solicitud = $this->sin->solicitudBase();
        unset($solicitud['codigoModalidad']);
        $solicitud['cuis'] = $cuis->cuis;

        $servicio = $this->sin->servicio('FacturacionSincronizacion');
        $resultados = [];

        foreach (self::METODOS as $metodo) {
            $nombre = self::nombre($metodo);

            try {
                $respuesta = $servicio->{$metodo}(['SolicitudSincronizacion' => $solicitud]);
                $arreglo = (array) $respuesta;
                $datos = reset($arreglo);
            } catch (Throwable $e) {
                $resultados[$nombre] = ['ok' => false, 'mensaje' => $e->getMessage()];

                continue;
            }

            if (! ($datos->transaccion ?? false)) {
                $resultados[$nombre] = [
                    'ok'      => false,
                    'mensaje' => json_encode($datos->mensajesList ?? $datos, JSON_UNESCAPED_UNICODE),
                ];

                continue;
            }

            SinCatalogo::updateOrCreate(
                ['catalogo' => $nombre],
                [
                    'datos'           => json_decode(json_encode($datos), true),
                    'sincronizado_en' => now(),
                ]
            );

            $resultados[$nombre] = ['ok' => true, 'mensaje' => 'Sincronizado'];
        }

        return $resultados;
    }
}
