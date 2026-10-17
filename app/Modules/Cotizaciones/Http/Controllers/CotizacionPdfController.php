<?php

namespace App\Modules\Cotizaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Cotizaciones\Services\CotizacionPdfService;
use Illuminate\Http\Request;

class CotizacionPdfController extends Controller
{
    public function __construct(private CotizacionPdfService $pdf)
    {
    }

    /** PDF para el personal (requiere iniciar sesión). */
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

        return $this->pdf->generar($cotizacion)->stream($cotizacion->numero_completo . '.pdf');
    }

    /** PDF para el cliente: se abre con el enlace firmado que va en el mensaje de WhatsApp. */
    public function publico(Cotizacion $cotizacion)
    {
        abort_unless(
            in_array($cotizacion->estado, [Cotizacion::APROBADA, Cotizacion::FACTURADA]),
            404
        );

        return $this->pdf->generar($cotizacion)->stream($cotizacion->numero_completo . '.pdf');
    }
}
