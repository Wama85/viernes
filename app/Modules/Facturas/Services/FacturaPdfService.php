<?php

namespace App\Modules\Facturas\Services;

use App\Modules\Empresa\Models\Empresa;
use App\Modules\Facturas\Models\Factura;
use App\Modules\Productos\Models\UnidadMedida;
use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Models\SinCufd;
use App\Support\MontoEnLetras;
use App\Support\PdfAyudas;
use Barryvdh\DomPDF\Facade\Pdf;

class FacturaPdfService
{
    private const CONSULTA_PILOTO = 'https://pilotosiat.impuestos.gob.bo/consulta/QR';
    private const CONSULTA_PRODUCCION = 'https://siat.impuestos.gob.bo/consulta/QR';

    public function generar(Factura $factura): \Barryvdh\DomPDF\PDF
    {
        $factura->loadMissing(['detalles']);

        $empresa = Empresa::datos();
        $detalles = $factura->detalles->sortBy('id_cotizacion_detalle')->values();

        $validada = $factura->estado_sin === Factura::SIN_VALIDADA && $factura->cuf;
        // Sin valor legal: aún no validada por el SIN, o validada en el ambiente de pruebas.
        $sinValorLegal = ! $validada || $factura->esDePrueba();

        $documento = $factura->cliente_documento
            . ($factura->cliente_complemento ? '-' . $factura->cliente_complemento : '');

        $textoQr = $validada
            ? $this->urlConsultaSin($factura, $empresa)
            : implode("\n", [
                'DOCUMENTO INTERNO - SIN VALOR LEGAL',
                'FACTURA ' . ($factura->numero ?? 'S/N'),
                'CLIENTE: ' . $factura->cliente_razon_social,
                'TOTAL: Bs ' . number_format((float) $factura->monto_total, 2),
            ]);

        $descuentoTotal = (float) $detalles->sum('descuento');
        $total = (float) $factura->monto_total;

        return Pdf::loadView('facturas.pdf', [
            'factura'        => $factura,
            'lineas'         => $this->lineas($detalles),
            'emisor'         => $this->emisor($factura, $empresa),
            'empresa'        => $empresa,
            'documento'      => $documento,
            'logo'           => PdfAyudas::imagen($empresa->logo),
            'qr'             => PdfAyudas::qr($textoQr),
            'enLetras'       => MontoEnLetras::convertir($total),
            'subtotalBruto'  => $total + $descuentoTotal,
            'descuentoTotal' => $descuentoTotal,
            'total'          => $total,
            'validada'       => (bool) $validada,
            'sinValorLegal'  => $sinValorLegal,
        ])->setPaper('a4');
    }

    /** Datos del emisor tal como se enviaron al SIN. */
    private function emisor(Factura $factura, Empresa $empresa): array
    {
        $cufd = $factura->cufd ? SinCufd::where('cufd', $factura->cufd)->first() : null;
        $cufd ??= SinCufd::actual();

        return [
            'razon_social' => config('sin.razon_social') ?: $empresa->nombre_empresa,
            'nit'          => config('sin.nit') ?: $empresa->nit,
            'direccion'    => $cufd?->direccion ?: $empresa->direccion,
            'telefono'     => config('sin.telefono') ?: $empresa->telefonos,
            'municipio'    => config('sin.municipio') ?: $empresa->ciudad_pais,
            'sucursal'     => (int) config('sin.sucursal'),
            'punto_venta'  => (int) ($factura->codigo_punto_venta ?? config('sin.punto_venta')),
        ];
    }

    /** Ítems con código de producto y nombre de la unidad de medida. */
    private function lineas($detalles): array
    {
        $codigos = UnidadMedida::whereIn('id_unidad_medida', $detalles->pluck('id_unidad_medida'))
            ->pluck('codigo_sin', 'id_unidad_medida');

        $nombres = $this->nombresUnidad();
        $lineas = [];

        foreach ($detalles as $d) {
            $codigoUnidad = (int) $codigos->get($d->id_unidad_medida, 0);

            $lineas[] = [
                'codigo'      => $d->id_producto,
                'cantidad'    => (float) $d->cantidad,
                'unidad'      => $nombres[$codigoUnidad] ?? '',
                'descripcion' => trim($d->descripcion . ($d->descripcion_adicional ? ' - ' . $d->descripcion_adicional : '')),
                'precio'      => (float) $d->precio_unitario,
                'descuento'   => (float) $d->descuento,
                'subtotal'    => (float) $d->subtotal,
            ];
        }

        return $lineas;
    }

    /** código SIN => descripción, leído del catálogo sincronizado ParametricaUnidadMedida. */
    private function nombresUnidad(): array
    {
        $mapa = [];
        $fila = SinCatalogo::where('catalogo', 'ParametricaUnidadMedida')->first();

        $recorrer = function ($nodo) use (&$recorrer, &$mapa) {
            if (! is_array($nodo)) {
                return;
            }

            if (isset($nodo['codigoClasificador'], $nodo['descripcion'])) {
                $mapa[(int) $nodo['codigoClasificador']] = $nodo['descripcion'];

                return;
            }

            foreach ($nodo as $hijo) {
                $recorrer($hijo);
            }
        };

        $recorrer((array) ($fila?->datos ?? []));

        return $mapa;
    }

    /** URL de consulta de la factura en el portal del SIN (es lo que lee el QR oficial). */
    private function urlConsultaSin(Factura $factura, Empresa $empresa): string
    {
        $base = $factura->esDePrueba() ? self::CONSULTA_PILOTO : self::CONSULTA_PRODUCCION;

        return $base . '?' . http_build_query([
            'nit'    => config('sin.nit') ?: $empresa->nit,
            'cuf'    => $factura->cuf,
            'numero' => $factura->numero,
            't'      => 2,
        ]);
    }
}
