<?php

namespace App\Modules\Sin\Services;

use App\Modules\Sin\Models\SinCufd;
use App\Modules\Sin\Models\SinCuis;
use App\Modules\Sin\Models\SinFacturaPrueba;
use App\Modules\Facturas\Models\Factura;
use RuntimeException;
use SoapFault;

class SinFacturaAnulacionService
{
    public const MOTIVOS = [1, 3, 4];

    public function __construct(private SinCliente $sin)
    {
    }

    public function anular(SinFacturaPrueba $factura, int $motivo): SinFacturaPrueba
    {
        if ($factura->estado !== SinFacturaPrueba::VALIDADA) {
            throw new RuntimeException("Solo se anulan facturas VALIDADAS (estado actual: {$factura->estado}).");
        }

        if (! in_array($motivo, self::MOTIVOS, true)) {
            throw new RuntimeException('El motivo de anulación debe ser 1, 3 o 4.');
        }

        $solicitud = $this->comunes($factura->cuf) + ['codigoMotivo' => $motivo];

        [$transaccion, $codigoEstado, $mensajes] = $this->invocar(
            'anulacionFactura',
            'SolicitudServicioAnulacionFactura',
            $solicitud
        );

        if ($transaccion) {
            $factura->update([
                'estado'           => SinFacturaPrueba::ANULADA,
                'motivo_anulacion' => $motivo,
                'codigo_estado'    => $codigoEstado,
                'mensajes'         => $mensajes,
            ]);
        } else {
            $factura->update(['codigo_estado' => $codigoEstado, 'mensajes' => $mensajes]);
        }

        return $factura->fresh();
    }

    public function revertir(SinFacturaPrueba $factura): SinFacturaPrueba
    {
        if ($factura->estado !== SinFacturaPrueba::ANULADA) {
            throw new RuntimeException("Solo se revierten facturas ANULADAS (estado actual: {$factura->estado}).");
        }

        // La reversión de una anulación se puede hacer una única vez por factura
        if ($factura->reversiones >= 1) {
            throw new RuntimeException('Esta factura ya tuvo su reversión; el SIN no permite una segunda.');
        }

        [$transaccion, $codigoEstado, $mensajes] = $this->invocar(
            'reversionAnulacionFactura',
            'SolicitudServicioReversionAnulacionFactura',
            $this->comunes($factura->cuf)
        );

        if ($transaccion) {
            $factura->update([
                'estado'           => SinFacturaPrueba::VALIDADA,
                'motivo_anulacion' => null,
                'reversiones'      => $factura->reversiones + 1,
                'codigo_estado'    => $codigoEstado,
                'mensajes'         => $mensajes,
            ]);
        } else {
            $factura->update(['codigo_estado' => $codigoEstado, 'mensajes' => $mensajes]);
        }

        return $factura->fresh();
    }
    /**
     * Anula en el SIN una factura real ya validada.
     *
     * @return array{0: bool, 1: ?int, 2: string} [aceptada, codigoEstado, mensaje]
     */
    public function anularFactura(Factura $factura, int $motivo): array
    {
        if ($factura->estado_sin !== Factura::SIN_VALIDADA) {
            throw new RuntimeException('Solo se anulan en el SIN las facturas validadas por el SIN.');
        }

        if (! $factura->cuf) {
            throw new RuntimeException('La factura no tiene CUF; no se puede anular en el SIN.');
        }

        if (! in_array($motivo, self::MOTIVOS, true)) {
            throw new RuntimeException('El motivo de anulación debe ser 1, 3 o 4.');
        }

        return $this->invocar(
            'anulacionFactura',
            'SolicitudServicioAnulacionFactura',
            $this->comunes($factura->cuf) + ['codigoMotivo' => $motivo]
        );
    }

    private function comunes(string $cuf): array
    {
        $cuis = SinCuis::actual();
        $cufd = SinCufd::actual();

        if (! $cuis) {
            throw new RuntimeException('No hay un CUIS vigente. Ejecuta: php artisan sin:cuis');
        }

        if (! $cufd) {
            throw new RuntimeException('No hay un CUFD vigente. Ejecuta: php artisan sin:cufd');
        }

        return $this->sin->solicitudBase() + [
            'codigoDocumentoSector' => 1,
            'codigoEmision'         => 1,
            'tipoFacturaDocumento'  => 1,
            'cuis'                  => $cuis->cuis,
            'cufd'                  => $cufd->cufd,
            'cuf'                   => $cuf,
        ];
    }

    /** @return array{0: bool, 1: ?int, 2: string} */
    private function invocar(string $operacion, string $campo, array $solicitud): array
    {
        try {
            $respuesta = $this->sin
                ->servicio('ServicioFacturacionCompraVenta')
                ->{$operacion}([$campo => $solicitud]);
        } catch (SoapFault $e) {
            throw new RuntimeException('Error al comunicarse con el SIN: ' . $e->getMessage());
        }

        $arreglo = (array) $respuesta;
        $datos = reset($arreglo);

        $transaccion = (bool) ($datos->transaccion ?? false);
        $codigoEstado = isset($datos->codigoEstado) ? (int) $datos->codigoEstado : null;

        $mensajes = $this->mensajes($datos->mensajesList ?? null);
        $descripcion = $datos->codigoDescripcion ?? '';

        $texto = trim($descripcion . ($mensajes ? ' | ' . $mensajes : ''), ' |');

        return [$transaccion, $codigoEstado, $texto !== '' ? $texto : json_encode($datos, JSON_UNESCAPED_UNICODE)];
    }

    private function mensajes(mixed $lista): string
    {
        if (! $lista) {
            return '';
        }

        if (is_object($lista)) {
            $lista = [$lista];
        }

        $textos = [];

        foreach ((array) $lista as $m) {
            $m = (object) $m;
            $textos[] = trim(($m->codigo ?? '') . ': ' . ($m->descripcion ?? ''), ': ');
        }

        return implode(' | ', array_filter($textos));
    }
}
