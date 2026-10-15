<?php

namespace App\Modules\Cotizaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Empresa\Models\Empresa;
use App\Support\MontoEnLetras;
use App\Support\PdfAyudas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CotizacionPdfController extends Controller
{
    public function __invoke(Request $request, Cotizacion $cotizacion)
    {
        $usuario = $request->user();

        abort_unless(
            (int) $usuario->id_rol === 1 || $cotizacion->id_usuario === $usuario->id_usuario,
            403
        );

        if (! in_array($cotizacion->estado, [Cotizacion::APROBADA, Cotizacion::FACTURADA])) {
            return back()->with('error', 'El PDF solo está disponible para cotizaciones aprobadas.');
        }

        $cotizacion->load(['cliente.tipoDocumento', 'detalles']);

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

        $pdf = Pdf::loadView('cotizaciones.pdf', [
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

        return $pdf->stream($cotizacion->numero_completo . '.pdf');
    }
}
