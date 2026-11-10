<?php

namespace App\Modules\Sin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sin\Models\SinEvento;
use App\Modules\Sin\Models\SinPaquete;
use App\Modules\Sin\Services\SinComunicacion;
use App\Modules\Sin\Services\SinContingenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ContingenciaController extends Controller
{
    public function __construct(private SinContingenciaService $servicio, private SinComunicacion $comunicacion)
    {
    }

    public function index()
    {
        $pendientes = $this->servicio->pendientes();

        return view('sin.contingencia', [
            'pendientes' => $pendientes,
            'plazo'      => $this->servicio->plazo($pendientes),
            'motivos'    => $this->servicio->motivos(),
            'conexion'   => $this->comunicacion->disponible(),
            'simulando'  => $this->comunicacion->simulando(),
            'motivoCaida' => $this->comunicacion->motivo(),
            'esPiloto'   => (int) config('sin.ambiente') === 2,
            'eventos'    => SinEvento::with('paquetes')->latest('id_sin_evento')->limit(10)->get(),
            'pasos'      => session('pasos_contingencia', []),
        ]);
    }

    /** Registra el evento, envía el paquete de facturas fuera de línea y consulta su validación. */
    public function cerrar(Request $request): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'integer', 'between:1,4']]);

        set_time_limit(300);

        try {
            $pasos = $this->servicio->cerrar((int) $datos['motivo']);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        $fallo = collect($pasos)->contains(fn ($p) => ! $p['ok']);

        return back()
            ->with('pasos_contingencia', $pasos)
            ->with($fallo ? 'error' : 'success', $fallo
                ? 'El cierre no se completó. Revisa los pasos.'
                : 'Contingencia cerrada: las facturas se enviaron al SIN.');
    }

    /** Vuelve a consultar al SIN la validación de un paquete que quedó pendiente. */
    public function validar(SinPaquete $paquete): RedirectResponse
    {
        try {
            $paquete = $this->servicio->validar($paquete);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            $paquete->estado === SinPaquete::RECHAZADO ? 'error' : 'success',
            'Paquete ' . $paquete->codigo_recepcion . ': ' . $paquete->estado . ($paquete->mensajes ? ' · ' . $paquete->mensajes : '')
        );
    }

    /** Solo en el ambiente de pruebas: simula la falta de conexión con el SIN. */
    public function simular(Request $request): RedirectResponse
    {
        abort_unless((int) config('sin.ambiente') === 2, 403);

        $activar = $request->boolean('activar');
        $this->comunicacion->simular($activar);

        return back()->with('success', $activar
            ? 'Simulación activada: las facturas nuevas se emitirán fuera de línea.'
            : 'Simulación desactivada: la comunicación con el SIN vuelve a ser real.');
    }
}
