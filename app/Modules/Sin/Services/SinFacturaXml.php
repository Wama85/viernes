<?php

namespace App\Modules\Sin\Services;

use DOMDocument;
use RuntimeException;

class SinFacturaXml
{
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /** Arma el XML respetando el orden en que vienen los campos. null => xsi:nil. */
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
        $this->campos($doc, $nodoCabecera, $cabecera);

        foreach ($detalles as $detalle) {
            $nodo = $doc->createElement('detalle');
            $raiz->appendChild($nodo);
            $this->campos($doc, $nodo, $detalle);
        }

        return $doc->saveXML();
    }

    /** Devuelve la lista de errores (vacía si el XML es válido). */
    public function validar(string $xml): array
    {
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

    private function campos(DOMDocument $doc, $padre, array $campos): void
    {
        foreach ($campos as $nombre => $valor) {
            $el = $doc->createElement($nombre);

            if ($valor === null) {
                $el->setAttributeNS(self::XSI, 'xsi:nil', 'true');
            } else {
                $texto = is_float($valor) ? number_format($valor, 2, '.', '') : (string) $valor;
                $el->appendChild($doc->createTextNode($texto));
            }

            $padre->appendChild($el);
        }
    }
}
