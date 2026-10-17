<?php

namespace App\Modules\Sin\Services;

use App\Modules\Sin\Models\SinCuis;
use App\Modules\Sin\Models\SinFacturaPrueba;
use RuntimeException;
use SoapFault;

class SinFacturaEnvioService
{
    private const ESTADO_VALIDADA  = 908;
    private const ESTADO_RECHAZADA = 902;

    public function __construct(private SinCliente $sin)
    {
    }

    public function enviar(SinFacturaPrueba $factura): SinFacturaPrueba
    {
        if ($factura->estado !== SinFacturaPrueba::GENERADA) {
            throw new RuntimeException("La factura ya fue enviada (estado {$factura->estado}).");
        }

        $cuis = SinCuis::actual();

        if (! $cuis) {
            throw new RuntimeException('No hay un CUIS vigente. Ejecuta: php artisan sin:cuis');
        }

        // Gzip del XML; PHP lo codifica a Base64 solo, porque el WSDL lo declara base64Binary
        $comprimido = gzencode($factura->xml, 9);

        $solicitud = $this->sin->solicitudBase() + [
            'codigoDocumentoSector' => 1,
            'codigoEmision'         => 1,
            'tipoFacturaDocumento'  => 1,
            'cuis'                  => $cuis->cuis,
            'cufd'                  => $factura->cufd,
            'archivo'               => $comprimido,
            'fechaEnvio'            => now()->format('Y-m-d\TH:i:s.v'),
            'hashArchivo'           => hash('sha256', $comprimido),
        ];

        try {
            $respuesta = $this->sin
                ->servicio('ServicioFacturacionCompraVenta')
                ->recepcionFactura(['SolicitudServicioRecepcionFactura' => $solicitud]);
        } catch (SoapFault $e) {
            $factura->update(['mensajes' => 'Error SOAP: ' . $e->getMessage()]);

            throw new RuntimeException('Error al comunicarse con el SIN: ' . $e->getMessage());
        }

        $arreglo = (array) $respuesta;
        $datos = reset($arreglo);

        $transaccion = (bool) ($datos->transaccion ?? false);
        $codigoEstado = isset($datos->codigoEstado) ? (int) $datos->codigoEstado : null;
        $mensajes = $this->mensajes($datos->mensajesList ?? null);

        if ($mensajes === '') {
            $mensajes = json_encode($datos, JSON_UNESCAPED_UNICODE);
        }

        $estado = SinFacturaPrueba::GENERADA;

        if ($transaccion && $codigoEstado === self::ESTADO_VALIDADA) {
            $estado = SinFacturaPrueba::VALIDADA;
        } elseif (! $transaccion || $codigoEstado === self::ESTADO_RECHAZADA) {
            $estado = SinFacturaPrueba::RECHAZADA;
        }

        $factura->update([
            'estado'           => $estado,
            'codigo_estado'    => $codigoEstado,
            'codigo_recepcion' => $datos->codigoRecepcion ?? null,
            'mensajes'         => $mensajes,
            'enviada_en'       => now(),
        ]);

        return $factura->fresh();
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
