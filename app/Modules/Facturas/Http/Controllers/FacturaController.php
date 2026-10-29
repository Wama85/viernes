<?php

namespace App\Modules\Facturas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Empresa\Models\Empresa;
use App\Modules\Facturas\Http\Requests\FacturaRequest;
use App\Modules\Facturas\Models\Factura;
use App\Modules\Productos\Services\StockService;
use App\Modules\Sin\Services\SinFacturaEmisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Modules\Facturas\Services\FacturaPdfService;
use App\Support\TotalFacturable;
use App\Modules\Sin\Services\SinFacturaAnulacionService;
use Throwable;

class FacturaController extends Controller
{
    public function __construct(
        private StockService $stock,
        private SinFacturaEmisionService $emision
    ) {
    }

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

        [$factura, $faltantes] = DB::transaction(function () use ($request, $cotizacion, $usuario, $esAdmin) {
            $bloqueada = Cotizacion::whereKey($cotizacion->getKey())->lockForUpdate()->firstOrFail();

            if ($motivo = $this->motivoBloqueo($bloqueada)) {
                throw ValidationException::withMessages(['items' => $motivo]);
            }

            $items = $this->resolverItems($bloqueada, $request->input('items'));

            // El total de los ítems elegidos debe poder facturarse al centavo con la v3 del SIN.
            $totalItems = round($items->sum('subtotal'), 2);

            if (! TotalFacturable::esAlcanzable($totalItems)) {
                throw ValidationException::withMessages([
                    'items' => 'La suma de los ítems elegidos (Bs ' . number_format($totalItems, 2)
                        . ') no se puede facturar exacta con el SIN: quedaría con 1 centavo de diferencia. '
                        . 'Elige otra combinación de ítems para esta factura (por ejemplo, agrega o quita uno).',
                ]);
            }

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

            // Aprobada desde el inicio: el stock se descuenta ahora
            $faltantes = $esAdmin
                ? $this->stock->descontar(StockService::DOC_FACTURA, $factura->id_factura, $items, $usuario->id_usuario)
                : [];

            return [$factura, $faltantes];
        });

        $exito = true;
        $mensaje = 'Factura registrada. Queda pendiente de aprobación del administrador.';

        if ($factura->estado === Factura::APROBADA) {
            // Ya confirmada la factura, se envía al SIN (fuera de la transacción).
            [$exito, $envio] = $this->enviarAlSin($factura);
            $mensaje = 'Factura registrada y aprobada. ' . $envio;
        }

        return redirect()->route('facturas.show', $factura)
            ->with($exito ? 'success' : 'error', $mensaje . $this->stock->mensajeFaltantes($faltantes))
            ->with('mostrar_pdf', $this->mostrarPdf($factura));
    }

    public function show(Factura $factura)
    {
        $this->verificarPropiedad($factura->cotizacion);

        $factura->load(['cotizacion', 'emisor', 'aprobador', 'detalles']);

        return view('facturas.show', compact('factura'));
    }

        /** PDF de la factura: se ve en el navegador o se descarga con ?descargar=1. */
    public function pdf(Request $request, Factura $factura, FacturaPdfService $servicio)
    {
        $this->verificarPropiedad($factura->cotizacion);

        abort_unless($factura->numero, 404, 'La factura aún no fue aprobada.');

        $pdf = $servicio->generar($factura);
        $nombre = 'factura-' . $factura->numero . '.pdf';

        return $request->boolean('descargar') ? $pdf->download($nombre) : $pdf->stream($nombre);
    }

    /** Al aprobar se asigna el número consecutivo, se descuenta el stock y se envía al SIN. */
    public function aprobar(Request $request, Factura $factura)
    {
        $idUsuario = $request->user()->id_usuario;

        $faltantes = DB::transaction(function () use ($factura, $idUsuario) {
            $actual = Factura::whereKey($factura->getKey())->lockForUpdate()->firstOrFail();

            if ($actual->estado !== Factura::PENDIENTE) {
                return null;
            }

            $actual->update([
                'numero'           => $this->siguienteNumero(),
                'estado'           => Factura::APROBADA,
                'id_aprobador'     => $idUsuario,
                'fecha_aprobacion' => now(),
            ]);

            return $this->stock->descontar(StockService::DOC_FACTURA, $actual->id_factura, $actual->detalles, $idUsuario);
        });

        if ($faltantes === null) {
            return back()->with('error', 'Solo una factura pendiente puede aprobarse.');
        }

        $factura = $factura->fresh();
        [$exito, $envio] = $this->enviarAlSin($factura);

        return back()->with(
            $exito ? 'success' : 'error',
            'Factura aprobada. ' . $envio . $this->stock->mensajeFaltantes($faltantes)
        )->with('mostrar_pdf', $this->mostrarPdf($factura));
    }

    /** Envía (o reintenta enviar) al SIN una factura aprobada. */
    public function enviarSin(Request $request, Factura $factura)
    {
        abort_unless((int) $request->user()->id_rol === 1, 403);

        

        // «con_excepcion»: el SIN no reconoce el NIT del cliente y se emite igual con código de excepción.
        [$exito, $mensaje] = $this->enviarAlSin($factura, $request->boolean('con_excepcion'));

        return back()
            ->with($exito ? 'success' : 'error', $mensaje)
            ->with('mostrar_pdf', $this->mostrarPdf($factura));
    }

    /**
     * Anular libera los ítems y devuelve el stock. Si el SIN ya la validó, primero se anula
     * en el SIN (con su motivo); si nunca llegó o fue rechazada, se anula solo aquí.
     */
    public function anular(Request $request, Factura $factura, SinFacturaAnulacionService $anulacion)
    {
        if ($factura->estado === Factura::ANULADA) {
            return back()->with('error', 'La factura ya está anulada.');
        }

        $validadaPorSin = $factura->estado_sin === Factura::SIN_VALIDADA;

        if (! $validadaPorSin && ! in_array($factura->estado_sin, [Factura::SIN_NO_ENVIADA, Factura::SIN_RECHAZADA], true)) {
            return back()->with('error', 'Esta factura no se puede anular en su estado actual.');
        }

        $datosSin = [];

        if ($validadaPorSin) {
            $request->validate(['motivo_sin' => ['required', 'in:1,3,4']]);

            try {
                [$aceptada, $codigoEstado, $mensaje] = $anulacion->anularFactura($factura, (int) $request->motivo_sin);
            } catch (Throwable $e) {
                Log::warning("SIN: no se pudo anular la factura {$factura->getKey()}: " . $e->getMessage());

                return back()->with('error', 'No se pudo anular en el SIN: ' . $e->getMessage());
            }

            if (! $aceptada) {
                return back()->with('error', 'El SIN no aceptó la anulación: ' . $mensaje);
            }

            $datosSin = [
                'codigo_estado' => $codigoEstado,
                'mensaje_sin'   => 'ANULADA EN EL SIN (motivo ' . $request->motivo_sin . '). ' . $mensaje,
            ];
        }

        $idUsuario = $request->user()->id_usuario;

        DB::transaction(function () use ($factura, $idUsuario, $datosSin) {
            $actual = Factura::whereKey($factura->getKey())->lockForUpdate()->firstOrFail();

            if ($actual->estado === Factura::APROBADA) {
                $this->stock->devolver(StockService::DOC_FACTURA, $actual->id_factura, $idUsuario, 'Anulación de la factura');
            }

            $actual->update(['estado' => Factura::ANULADA] + $datosSin);
        });

        return back()->with('success', $validadaPorSin
            ? 'Factura anulada en el SIN y en el sistema. Sus ítems quedaron disponibles.'
            : 'Factura anulada. Sus ítems quedaron disponibles.');
    }

    // ------------------------------------------------------------------

    /** ¿Ya hay autorización para enviar facturas al SIN? (SIN_ENVIAR_FACTURAS en el .env) */
    private function envioActivo(): bool
    {
        return (bool) config('facturacion.enviar_al_sin');
    }

    /** Mientras no haya autorización, la factura aprobada se muestra como documento sin valor legal. */
    private function mostrarPdf(Factura $factura): bool
    {
        return $factura->estado === Factura::APROBADA && $factura->estado_sin !== Factura::SIN_VALIDADA;
    }

    /** @return array{0: bool, 1: string} [éxito, texto para el usuario] */
    private function enviarAlSin(Factura $factura, bool $conExcepcion = false): array
    {
        if (! $this->envioActivo()) {
            return [false, 'Todavía no es posible enviar la factura al SIN: el sistema aún no cuenta con la autorización de facturación electrónica. Se muestra el documento interno, sin valor legal.'];
        }

        try {
            $enviada = $this->emision->emitir($factura, $conExcepcion);
        } catch (Throwable $e) {
            Log::warning("SIN: no se pudo enviar la factura {$factura->getKey()}: " . $e->getMessage());

            return [false, 'No se pudo enviar al SIN: ' . $e->getMessage() . ' Puedes reintentar desde la factura.'];
        }

        if ($enviada->estado_sin === Factura::SIN_VALIDADA) {
            return [true, $enviada->esDePrueba()
                ? 'Validada por el SIN en el ambiente de pruebas (sin valor legal).'
                : 'Validada por el SIN.'];
        }

        if ((int) $enviada->codigo_estado === -1) {
            return [false, 'El SIN tuvo un error interno y no pudo procesar la factura (suele ser temporal). Reintenta en unos minutos. Detalle: ' . $enviada->mensaje_sin];
        }

        return [false, 'El SIN no la validó: ' . $enviada->mensaje_sin];
    }

    private function verificarPropiedad(Cotizacion $cotizacion): void
    {
        $usuario = request()->user();

        abort_unless(
            (int) $usuario->id_rol === 1 || $cotizacion->id_usuario === $usuario->id_usuario,
            403
        );
    }

    /** Consecutivo general, sin reinicio por año. Continúa después de las facturas de prueba del SIN. */
    private function siguienteNumero(): int
    {
        $propias = (int) Factura::lockForUpdate()->max('numero');
        $deLasPruebas = (int) DB::table('sin_facturas_prueba')->max('numero');

        return max($propias, $deLasPruebas) + 1;
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
