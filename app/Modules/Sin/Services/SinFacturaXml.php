<?php

namespace App\Modules\Sin\Services;

use DOMDocument;
use RuntimeException;

class SinFacturaXml
{
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * Validar contra el XSD local (que es el de la v2). Mientras exploramos la estructura de la v3,
     * la valida el propio SIN. Ponlo en true cuando tengamos el XSD de la v3.
     */
    private const VALIDAR_LOCAL = false;

    /** Campos que, si están vacíos, se escriben como elemento vacío (<campo></campo>) en vez de xsi:nil. */
    private const VACIO_EN_BLANCO = ['complemento'];

    /** v3: campos que ya no van en su lugar de la v2 (o que ya no existen). */
    private const QUITAR_V3 = ['montoTotal', 'montoTotalSujetoIva', 'cafc'];

    /** Arma el XML respetando el orden en que vienen los campos. */
    public function construir(array $cabecera, array $detalles): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->xmlStandalone = true;
        $doc->formatOutput = false;

        $raiz = $doc->createElement('facturaComputarizadaCompraVenta');
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI);
        $raiz->setAttributeNS(self::XSI, 'xsi:noNamespaceSchemaLocation', 'facturaComputarizadaCompraVenta.xsd');
        $doc->appendChild($raiz);

        $nodoCabecera = $doc->createElement('cabecera');
        $raiz->appendChild($nodoCabecera);
        $this->campos($doc, $nodoCabecera, $this->adaptarCabeceraV3($cabecera, $detalles));

        foreach ($detalles as $detalle) {
            $nodo = $doc->createElement('detalle');
            $raiz->appendChild($nodo);
            $this->campos($doc, $nodo, $this->adaptarDetalleV3($detalle));
        }

        return $doc->saveXML();
    }

    /** Devuelve la lista de errores (vacía si el XML es válido). */
    public function validar(string $xml): array
    {
        if (! self::VALIDAR_LOCAL) {
            return [];
        }

        $ruta = storage_path('app/sin/xsd/facturaComputarizadaCompraVenta.xsd');

        if (! is_file($ruta)) {
            throw new RuntimeException('Falta el XSD en: ' . $ruta);
        }

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $ok = $doc->schemaValidate($ruta);

        $errores = [];
        foreach (libxml_get_errors() as $e) {
            $errores[] = trim($e->message) . ' (línea ' . $e->line . ')';
        }

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if ($ok) {
            return [];
        }

        return $errores ?: ['El XML no cumple el esquema.'];
    }

    /**
     * v3: agrega los campos nuevos de la cabecera, en su lugar, y quita los que se movieron o ya no existen.
     *
     * Si la cabecera trae 'ivaFijo' (facturas reales, cuyo precio ya incluye el IVA), ese es el IVA y el
     * monto a pagar es neto + IVA, de modo que el total coincide con el de la cotización. Sin 'ivaFijo'
     * el IVA es el 13 % del neto (facturas de prueba del SIN).
     */
    private function adaptarCabeceraV3(array $cabecera, array $detalles): array
    {
        $ivaFijo = $cabecera['ivaFijo'] ?? null;
        unset($cabecera['ivaFijo']);

        $neto = round(array_sum(array_column($detalles, 'subTotal')), 2);
        $descuentoAdicional = (float) ($cabecera['descuentoAdicional'] ?? 0);
        $totalNeto = round($neto - $descuentoAdicional, 2);
        $iva = $ivaFijo !== null ? round((float) $ivaFijo, 2) : round($totalNeto * 0.13, 2);
        $montoAPagar = round($totalNeto + $iva, 2);

        // La v3 espera "0" cuando no hay complemento.
        if (array_key_exists('complemento', $cabecera) && ($cabecera['complemento'] === null || $cabecera['complemento'] === '')) {
            $cabecera['complemento'] = '0';
        }

        // El total en moneda es el monto a pagar (con IVA), por el tipo de cambio.
        if (array_key_exists('montoTotalMoneda', $cabecera)) {
            $cabecera['montoTotalMoneda'] = round($montoAPagar * (float) ($cabecera['tipoCambio'] ?? 1), 2);
        }

        // Van en el lugar donde en la v2 iba montoTotal.
        $nuevos = [
            'subTotalNeto'    => $neto,
            'totalPrecioNeto' => $totalNeto,
            'iva'             => $iva,
            'montoaPagar'     => $montoAPagar,
        ];

        $resultado = [];

        foreach ($cabecera as $nombre => $valor) {
            if ($nombre === 'montoTotal') {
                foreach ($nuevos as $campo => $valorNuevo) {
                    if (! array_key_exists($campo, $cabecera)) {
                        $resultado[$campo] = $valorNuevo;
                    }
                }
            }

            if ($nombre === 'codigoDocumentoSector' && ! array_key_exists('codigoInterno', $cabecera)) {
                $resultado['codigoInterno'] = 'C';
            }

            if (in_array($nombre, self::QUITAR_V3, true)) {
                continue;
            }

            $resultado[$nombre] = $valor;
        }

        return $resultado;
    }

    /** v3: en cada ítem, precioUnitario pasa a llamarse precioNetoUnitario. */
    private function adaptarDetalleV3(array $detalle): array
    {
        $resultado = [];

        foreach ($detalle as $nombre => $valor) {
            $resultado[$nombre === 'precioUnitario' ? 'precioNetoUnitario' : $nombre] = $valor;
        }

        return $resultado;
    }

    private function campos(DOMDocument $doc, $padre, array $campos): void
    {
        foreach ($campos as $nombre => $valor) {
            $el = $doc->createElement($nombre);

            if ($valor === null) {
                if (! in_array($nombre, self::VACIO_EN_BLANCO, true)) {
                    $el->setAttributeNS(self::XSI, 'xsi:nil', 'true');
                }
            } else {
                $texto = is_float($valor) ? number_format($valor, 2, '.', '') : (string) $valor;
                $el->appendChild($doc->createTextNode($texto));
            }

            $padre->appendChild($el);
        }
    }
}
