<?php

namespace App\Modules\Cotizaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cotizaciones\Models\Cotizacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SeguimientoController extends Controller
{
    public function marcarEnviada(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless(
            (int) $usuario->id_rol === 1 || $cotizacion->id_usuario === $usuario->id_usuario,
            403
        );

        if (! $cotizacion->puedeMarcarEnviada()) {
            return back()->with('error', 'Solo una cotización aprobada, vigente y no enviada puede marcarse como enviada.');
        }

        $cotizacion->forceFill(['fecha_envio' => now()])->save();

        return back()->with('success', 'Cotización marcada como enviada. Ya puedes emitir recibos.');
    }
}
