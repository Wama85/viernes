<?php

namespace App\Modules\Cotizaciones\Services;

use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Empresa\Models\Empresa;
use App\Support\MontoEnLetras;
use App\Support\PdfAyudas;
use Barryvdh\DomPDF\Facade\Pdf;

class CotizacionPdfService
{
    /** Arma el PDF de la cotización (se usa para ver, descargar y adjuntar al correo). */
    public function generar(Cotizacion $cotizacion): \Barryvdh\DomPDF\PDF
    {
        // El producto y su categoría se cargan para agrupar las líneas por categoría en el PDF.
        $cotizacion->loadMissing(['cliente.tipoDocumento', 'detalles.producto.categoria']);

        $cliente = $cotizacion->cliente;
        $empresa = Empresa::datos();

        $documento = $cliente->documento_identidad
            . ($cliente->complemento ? '-' . $cliente->complemento : '');

        $textoQr = implode("\n", [
            'COTIZACIÓN ' . $cotizacion->numero_completo,
            'CLIENTE: ' . $cliente->razon_social,
            'NIT/DOC: ' . $documento,
            'TOTAL: Bs ' . number_format($cotizacion->total, 2),
            'VÁLIDA HASTA: ' . $cotizacion->fecha_vencimiento->format('d/m/Y H:i'),
        ]);

        return Pdf::loadView('cotizaciones.pdf', [
            'cotizacion'   => $cotizacion,
            'cliente'      => $cliente,
            'documento'    => $documento,
            'empresa'      => $empresa,
            'logo'         => PdfAyudas::imagen($empresa->logo),
            'sello'        => PdfAyudas::imagen($empresa->sello),
            'qr'           => PdfAyudas::qr($textoQr),
            'enLetras'     => MontoEnLetras::convertir((float) $cotizacion->total),
            'conDescuento' => $cotizacion->detalles->sum('descuento') > 0,
        ])->setPaper('a4');
    }
}
