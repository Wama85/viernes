<?php

namespace App\Modules\Recibos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Empresa\Models\Empresa;
use App\Modules\Recibos\Http\Requests\ReciboRequest;
use App\Modules\Recibos\Models\Recibo;
use App\Support\MontoEnLetras;
use App\Support\PdfAyudas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Modules\Facturas\Models\Factura;

class ReciboController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();
        $anio = $request->query('anio');
        $estado = $request->query('estado');

        $recibos = Recibo::with(['cotizacion.cliente', 'emisor'])
            ->when((int) $usuario->id_rol !== 1, function ($q) use ($usuario) {
                $q->whereHas('cotizacion', fn ($c) => $c->where('id_usuario', $usuario->id_usuario));
            })
            ->when($anio, fn ($q) => $q->where('anio', $anio))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('anio')
            ->orderByDesc('numero')
            ->paginate(15)
            ->withQueryString();

        $anios = Recibo::query()->select('anio')->distinct()->orderByDesc('anio')->pluck('anio');

        return view('recibos.index', compact('recibos', 'anios', 'anio', 'estado'));
    }

    public function create(Cotizacion $cotizacion)
    {
        $this->verificarPropiedad($cotizacion);

        if ($motivo = $this->motivoBloqueo($cotizacion)) {
            return redirect()->route('cotizaciones.show', $cotizacion)->with('error', $motivo);
        }

        $cotizacion->load('cliente');

        return view('recibos.create', [
            'cotizacion' => $cotizacion,
            'items'      => $cotizacion->detallesDisponibles(),
        ]);
    }

    public function store(ReciboRequest $request, Cotizacion $cotizacion)
    {
        $this->verificarPropiedad($cotizacion);

        $usuario = $request->user();
        $esAdmin = (int) $usuario->id_rol === 1;

        $recibo = DB::transaction(function () use ($request, $cotizacion, $usuario, $esAdmin) {
            // Se bloquea la cotización para que dos personas no tomen los mismos ítems
            $bloqueada = Cotizacion::whereKey($cotizacion->getKey())->lockForUpdate()->firstOrFail();

            if ($motivo = $this->motivoBloqueo($bloqueada)) {
                throw ValidationException::withMessages(['items' => $motivo]);
            }

            $items = $this->resolverItems($bloqueada, $request->input('items'));

            $anio = now()->year;
            $numero = ((int) Recibo::where('anio', $anio)->lockForUpdate()->max('numero')) + 1;

            $recibo = Recibo::create([
                'anio'             => $anio,
                'numero'           => $numero,
                'id_cotizacion'    => $bloqueada->id_cotizacion,
                'id_usuario'       => $usuario->id_usuario,
                'fecha_emision'    => now(),
                'monto'            => round($items->sum('subtotal'), 2),
                'forma_pago'       => $request->input('forma_pago'),
                'referencia_pago'  => $request->input('referencia_pago'),
                'estado'           => $esAdmin ? Recibo::APROBADO : Recibo::PENDIENTE,
                'id_aprobador'     => $esAdmin ? $usuario->id_usuario : null,
                'fecha_aprobacion' => $esAdmin ? now() : null,
            ]);

            $recibo->detalles()->attach($items->pluck('id_cotizacion_detalle')->all());

            return $recibo;
        });

        // Aprobado: directo al PDF
        if ($recibo->estado === Recibo::APROBADO) {
            return redirect()->route('recibos.pdf', $recibo);
        }

        return redirect()->route('recibos.show', $recibo)
            ->with('success', 'Recibo emitido. Queda pendiente de aprobación del administrador.');
    }

    public function show(Recibo $recibo)
    {
        $this->verificarPropiedad($recibo->cotizacion);

        $recibo->load(['cotizacion.cliente', 'emisor', 'aprobador', 'detalles']);

        return view('recibos.show', compact('recibo'));
    }

    public function edit(Recibo $recibo)
    {
        if ($recibo->estado === Recibo::ANULADO) {
            return redirect()->route('recibos.show', $recibo)->with('error', 'Un recibo anulado no se puede editar.');
        }

        $recibo->load(['cotizacion.cliente', 'detalles']);

        return view('recibos.edit', [
            'recibo'     => $recibo,
            'cotizacion' => $recibo->cotizacion,
            'items'      => $this->itemsEditables($recibo->cotizacion, $recibo),
        ]);
    }

    public function update(ReciboRequest $request, Recibo $recibo)
    {
        if ($recibo->estado === Recibo::ANULADO) {
            return redirect()->route('recibos.show', $recibo)->with('error', 'Un recibo anulado no se puede editar.');
        }

        DB::transaction(function () use ($request, $recibo) {
            $cotizacion = Cotizacion::whereKey($recibo->id_cotizacion)->lockForUpdate()->firstOrFail();

            $items = $this->resolverItems($cotizacion, $request->input('items'), $recibo);

            $recibo->update([
                'forma_pago'      => $request->input('forma_pago'),
                'referencia_pago' => $request->input('referencia_pago'),
                'monto'           => round($items->sum('subtotal'), 2),
            ]);

            $recibo->detalles()->sync($items->pluck('id_cotizacion_detalle')->all());
        });

        return redirect()->route('recibos.show', $recibo)->with('success', 'Recibo actualizado.');
    }

    public function aprobar(Request $request, Recibo $recibo)
    {
        if ($recibo->estado !== Recibo::PENDIENTE) {
            return back()->with('error', 'Solo un recibo pendiente puede aprobarse.');
        }

        $recibo->update([
            'estado'           => Recibo::APROBADO,
            'id_aprobador'     => $request->user()->id_usuario,
            'fecha_aprobacion' => now(),
        ]);

        return back()->with('success', 'Recibo aprobado.');
    }

    /** Al anular, sus ítems quedan libres para otro recibo. */
    public function anular(Recibo $recibo)
    {
        if ($recibo->estado === Recibo::ANULADO) {
            return back()->with('error', 'El recibo ya está anulado.');
        }

        $recibo->update(['estado' => Recibo::ANULADO]);

        return back()->with('success', 'Recibo anulado. Sus ítems quedaron disponibles.');
    }

    public function pdf(Recibo $recibo)
    {
        $this->verificarPropiedad($recibo->cotizacion);

        if ($recibo->estado !== Recibo::APROBADO) {
            return redirect()->route('recibos.show', $recibo)
                ->with('error', 'El PDF solo está disponible para recibos aprobados.');
        }

        $recibo->load(['cotizacion.cliente', 'detalles']);

        $cliente = $recibo->cotizacion->cliente;
        $empresa = Empresa::datos();

        $documento = $cliente->documento_identidad
            . ($cliente->complemento ? '-' . $cliente->complemento : '');

        $textoQr = implode("\n", [
            'RECIBO ' . $recibo->numero_completo,
            'CLIENTE: ' . $cliente->razon_social,
            'NIT/CI: ' . $documento,
            'MONTO: Bs ' . number_format($recibo->monto, 2),
            'FECHA: ' . $recibo->fecha_emision->format('d/m/Y H:i'),
        ]);

        $pdf = Pdf::loadView('recibos.pdf', [
            'recibo'       => $recibo,
            'cotizacion'   => $recibo->cotizacion,
            'cliente'      => $cliente,
            'documento'    => $documento,
            'empresa'      => $empresa,
            'logo'         => PdfAyudas::imagen($empresa->logo),
            'sello'        => PdfAyudas::imagen($empresa->sello),
            'qr'           => PdfAyudas::qr($textoQr),
            'enLetras'     => MontoEnLetras::convertir((float) $recibo->monto),
            'conDescuento' => $recibo->detalles->sum('descuento') > 0,
        ])->setPaper('a4');

        return $pdf->stream($recibo->numero_completo . '.pdf');
    }

    // ------------------------------------------------------------------

    /** Administrador siempre; vendedor solo sobre sus cotizaciones. */
    private function verificarPropiedad(Cotizacion $cotizacion): void
    {
        $usuario = auth()->user();

        abort_unless(
            (int) $usuario->id_rol === 1 || $cotizacion->id_usuario === $usuario->id_usuario,
            403
        );
    }

    /** Devuelve el motivo por el que no se puede emitir un recibo, o null si se puede. */
    private function motivoBloqueo(Cotizacion $cotizacion): ?string
    {
        if ($cotizacion->estado !== Cotizacion::APROBADA) {
            return 'Solo una cotización aprobada puede emitir recibos.';
        }
        if ($cotizacion->estaVencida()) {
            return 'La cotización está vencida: ya no se pueden emitir recibos de sus ítems pendientes.';
        }
        if (! $cotizacion->fueEnviada()) {
            return 'La cotización aún no fue enviada al cliente.';
        }
        if ($cotizacion->detallesDisponibles()->isEmpty()) {
            return 'Todos los ítems de esta cotización ya tienen recibo.';
        }

        return null;
    }

    /** Ítems libres (sin recibo ni factura) más los que ya pertenecen a este recibo. */
    private function itemsEditables(Cotizacion $cotizacion, Recibo $recibo): Collection
    {
        return $cotizacion->detalles()
            ->where(function ($q) use ($recibo) {
                $q->where(function ($libres) {
                    $libres->whereDoesntHave('recibos', fn ($r) => $r->where('recibos.estado', '!=', Recibo::ANULADO))
                           ->whereDoesntHave('facturas', fn ($f) => $f->where('facturas.estado', '!=', Factura::ANULADA));
                })->orWhereHas('recibos', fn ($r) => $r->where('recibos.id_recibo', $recibo->id_recibo));
            })
            ->get();
    }

    /** Valida que los ítems enviados existan, sean de la cotización y estén libres. */
    private function resolverItems(Cotizacion $cotizacion, array $ids, ?Recibo $recibo = null): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $permitidos = $recibo
            ? $this->itemsEditables($cotizacion, $recibo)
            : $cotizacion->detallesDisponibles();

        $items = $permitidos->whereIn('id_cotizacion_detalle', $ids)->values();

        if ($items->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'items' => 'Hay ítems que no son válidos o que ya pertenecen a otro recibo.',
            ]);
        }

        return $items;
    }
}
