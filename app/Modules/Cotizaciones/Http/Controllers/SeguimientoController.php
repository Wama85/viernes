<?php

namespace App\Modules\Cotizaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\CotizacionEnviada;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Cotizaciones\Services\CotizacionPdfService;
use App\Support\Whatsapp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class SeguimientoController extends Controller
{
    public function __construct(private CotizacionPdfService $pdf)
    {
    }

    /** Envía el PDF por correo a los correos válidos del cliente. */
    public function correo(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        if ($error = $this->verificar($request, $cotizacion)) {
            return back()->with('error', $error);
        }

        $cotizacion->load(['cliente.tipoDocumento', 'cliente.correos', 'detalles']);

        $correos = $cotizacion->cliente->correos
            ->pluck('correo')
            ->filter(fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if (empty($correos)) {
            return back()->with('error', 'El cliente no tiene un correo válido registrado.');
        }

        try {
            $binario = $this->pdf->generar($cotizacion)->output();
            Mail::to($correos)->send(new CotizacionEnviada($cotizacion, $binario));
        } catch (Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'No se pudo enviar el correo. Revisa la configuración de correo e inténtalo de nuevo.'
                . (config('app.debug') ? ' (' . $e->getMessage() . ')' : '')
            );
        }

        $this->registrarEnvio($cotizacion);

        return back()->with('success', 'Cotización enviada a: ' . implode(', ', $correos) . '.');
    }

    /** Abre WhatsApp con el mensaje y el enlace seguro al PDF. */
    public function whatsapp(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        if ($error = $this->verificar($request, $cotizacion)) {
            return back()->with('error', $error);
        }

        $cotizacion->load(['cliente', 'cliente.telefonos']);

        $numero = Whatsapp::primero($cotizacion->cliente->telefonos->pluck('telefono'));

        if (! $numero) {
            return back()->with('error', 'El cliente no tiene un celular válido registrado para WhatsApp.');
        }

        $enlace = URL::temporarySignedRoute(
            'cotizaciones.documento',
            now()->addDays(7),
            ['cotizacion' => $cotizacion]
        );

        $mensaje = "Hola {$cotizacion->cliente->razon_social}, le saludamos de " . config('app.name') . ".\n\n"
            . "Le enviamos la cotización {$cotizacion->numero_completo} por Bs "
            . number_format($cotizacion->total, 2)
            . ", válida hasta el " . $cotizacion->fecha_vencimiento->format('d/m/Y H:i') . ".\n\n"
            . "Puede ver y descargar el PDF aquí:\n{$enlace}";

        $this->registrarEnvio($cotizacion);

        return redirect()->away('https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje));
    }

    /** Envío manual: marca como enviada y entrega el PDF para que lo envíes por tu medio. */
    public function manual(Request $request, Cotizacion $cotizacion)
    {
        if ($error = $this->verificar($request, $cotizacion)) {
            return back()->with('error', $error);
        }

        $this->registrarEnvio($cotizacion);

        return $this->pdf->generar($cotizacion)->stream($cotizacion->numero_completo . '.pdf');
    }

    // ------------------------------------------------------------------

    /** Devuelve un mensaje de error si la cotización no puede enviarse; null si todo está bien. */
    private function verificar(Request $request, Cotizacion $cotizacion): ?string
    {
        $usuario = $request->user();

        abort_unless(
            (int) $usuario->id_rol === 1 || $cotizacion->id_usuario === $usuario->id_usuario,
            403
        );

        if ($cotizacion->estado !== Cotizacion::APROBADA) {
            return 'Solo una cotización aprobada puede enviarse al cliente.';
        }

        if ($cotizacion->fecha_vencimiento->isPast()) {
            return 'La cotización está vencida y ya no puede enviarse.';
        }

        return null;
    }

    /** La fecha de envío se guarda solo la primera vez; los reenvíos no la modifican. */
    private function registrarEnvio(Cotizacion $cotizacion): void
    {
        if (! $cotizacion->fecha_envio) {
            $cotizacion->forceFill(['fecha_envio' => now()])->save();
        }
    }
}
