<?php

namespace App\Modules\Facturas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Empresa\Models\Empresa;
use App\Modules\Facturas\Http\Requests\FacturaRequest;
use App\Modules\Facturas\Models\Factura;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FacturaController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();
        $estado = $request->query('estado');

        $facturas = Factura::with(['cotizacion', 'emisor'])
            ->when((int) $usuario->id_rol !== 1, function ($q) use ($usuario) {
                $q->whereHas('cotizacion', fn ($c) => $c->where('id_usuario', $usuario->id_usuario));
            })
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('id_factura')
            ->paginate(15)
            ->withQueryString();

        return view('facturas.index', compact('facturas', 'estado'));
    }

    public function create(Cotizacion $cotizacion)
    {
        $this->verificarPropiedad($cotizacion);

        if ($motivo = $this->motivoBloqueo($cotizacion)) {
            return redirect()->route('cotizaciones.show', $cotizacion)->with('error', $motivo);
        }

        $cotizacion->load('cliente');

        return view('facturas.create', [
            'cotizacion' => $cotizacion,
            'items'      => $cotizacion->detallesDisponibles(),
        ]);
    }

    public function store(FacturaRequest $request, Cotizacion $cotizacion)
    {
        $this->verificarPropiedad($cotizacion);

        $usuario = $request->user();
        $esAdmin = (int) $usuario->id_rol === 1;

        $factura = DB::transaction(function () use ($request, $cotizacion, $usuario, $esAdmin) {
            $bloqueada = Cotizacion::whereKey($cotizacion->getKey())->lockForUpdate()->firstOrFail();

            if ($motivo = $this->motivoBloqueo($bloqueada)) {
                throw ValidationException::withMessages(['items' => $motivo]);
            }

            $items = $this->resolverItems($bloqueada, $request->input('items'));
            $cliente = $bloqueada->cliente()->with(['tipoDocumento', 'correos'])->first();

            $factura = Factura::create([
                'numero'                    => $esAdmin ? $this->siguienteNumero() : null,
                'id_cotizacion'             => $bloqueada->id_cotizacion,
                'id_usuario'                => $usuario->id_usuario,
                'fecha_emision'             => now(),
                // Datos del cliente congelados
                'cliente_razon_social'      => $cliente->razon_social,
                'cliente_id_tipo_documento' => $cliente->tipoDocumento?->getKey(),
                'cliente_documento'         => $cliente->documento_identidad,
                'cliente_complemento'       => $cliente->complemento,
                'cliente_correo'            => $cliente->correos->first()?->correo,
                'monto_total'               => round($items->sum('subtotal'), 2),
                'forma_pago'                => $request->input('forma_pago'),
                'estado'                    => $esAdmin ? Factura::APROBADA : Factura::PENDIENTE,
                'id_aprobador'              => $esAdmin ? $usuario->id_usuario : null,
                'fecha_aprobacion'          => $esAdmin ? now() : null,
                'estado_sin'                => Factura::SIN_NO_ENVIADA,
            ]);

            $factura->detalles()->attach($items->pluck('id_cotizacion_detalle')->all());

            return $factura;
        });

        $mensaje = $factura->estado === Factura::APROBADA
            ? 'Factura registrada y aprobada. Queda pendiente de enviarse al SIN.'
            : 'Factura registrada. Queda pendiente de aprobación del administrador.';

        return redirect()->route('facturas.show', $factura)->with('success', $mensaje);
    }

    public function show(Factura $factura)
    {
        $this->verificarPropiedad($factura->cotizacion);

        $factura->load(['cotizacion', 'emisor', 'aprobador', 'detalles']);

        return view('facturas.show', compact('factura'));
    }

    /** Al aprobar se asigna el número consecutivo. */
    public function aprobar(Request $request, Factura $factura)
    {
        DB::transaction(function () use ($request, $factura) {
            $actual = Factura::whereKey($factura->getKey())->lockForUpdate()->firstOrFail();

            if ($actual->estado !== Factura::PENDIENTE) {
                return;
            }

            $actual->update([
                'numero'           => $this->siguienteNumero(),
                'estado'           => Factura::APROBADA,
                'id_aprobador'     => $request->user()->id_usuario,
                'fecha_aprobacion' => now(),
            ]);
        });

        return back()->with('success', 'Factura aprobada.');
    }

    /** Anular libera los ítems. Una vez enviada al SIN, la anulación debe hacerse por el SIN. */
    public function anular(Factura $factura)
    {
        if ($factura->estado === Factura::ANULADA) {
            return back()->with('error', 'La factura ya está anulada.');
        }

        if ($factura->estado_sin !== Factura::SIN_NO_ENVIADA) {
            return back()->with('error', 'Esta factura ya fue enviada al SIN: debe anularse a través del SIN.');
        }

        $factura->update(['estado' => Factura::ANULADA]);

        return back()->with('success', 'Factura anulada. Sus ítems quedaron disponibles.');
    }

    // ------------------------------------------------------------------

    private function verificarPropiedad(Cotizacion $cotizacion): void
    {
        $usuario = auth()->user();

        abort_unless(
            (int) $usuario->id_rol === 1 || $cotizacion->id_usuario === $usuario->id_usuario,
            403
        );
    }

    /** Consecutivo general, sin reinicio por año (así lo exige el SIN). */
    private function siguienteNumero(): int
    {
        return ((int) Factura::lockForUpdate()->max('numero')) + 1;
    }

    private function motivoBloqueo(Cotizacion $cotizacion): ?string
    {
        if ($cotizacion->estado !== Cotizacion::APROBADA) {
            return 'Solo una cotización aprobada puede facturarse.';
        }
        if ($cotizacion->estaVencida()) {
            return 'La cotización está vencida: ya no se pueden facturar sus ítems pendientes.';
        }
        if (! $cotizacion->fueEnviada()) {
            return 'La cotización aún no fue enviada al cliente.';
        }
        if ($cotizacion->detallesDisponibles()->isEmpty()) {
            return 'Todos los ítems de esta cotización ya tienen recibo o factura.';
        }
        if (! Empresa::datos()->nit) {
            return 'Registra el NIT de la empresa en la pantalla Empresa antes de facturar.';
        }
        if (! $cotizacion->cliente->documento_identidad) {
            return 'El cliente no tiene NIT/CI registrado.';
        }

        return null;
    }

    private function resolverItems(Cotizacion $cotizacion, array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $items = $cotizacion->detallesDisponibles()
            ->whereIn('id_cotizacion_detalle', $ids)
            ->values();

        if ($items->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'items' => 'Hay ítems que no son válidos o que ya pertenecen a un recibo o factura.',
            ]);
        }

        return $items;
    }
}
