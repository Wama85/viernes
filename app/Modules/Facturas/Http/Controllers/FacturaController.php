<?php

namespace App\Modules\Facturas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\FacturaEnviada;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Empresa\Models\Empresa;
use App\Modules\Facturas\Http\Requests\FacturaRequest;
use App\Modules\Facturas\Models\Factura;
use App\Modules\Productos\Services\StockService;
use App\Modules\Recibos\Models\Recibo;
use App\Modules\Sin\Services\SinFacturaEmisionService;
use App\Modules\Sin\Services\SinNitService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use App\Modules\Facturas\Services\FacturaPdfService;
use App\Support\TotalFacturable;
use App\Modules\Sin\Services\SinFacturaAnulacionService;
use Throwable;

class FacturaController extends Controller
{
    public function __construct(
        private StockService $stock,
        private SinFacturaEmisionService $emision,
        private SinNitService $nit
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

        $cotizacion->load('cliente.tipoDocumento');

        return view('facturas.create', [
            'cotizacion' => $cotizacion,
            'items'      => $cotizacion->detallesDisponibles(),
            'nitEstado'  => $this->esNit($cotizacion->cliente)
                ? $this->nit->verificar((string) $cotizacion->cliente->documento_identidad)
                : null,
        ]);
    }

    public function store(FacturaRequest $request, Cotizacion $cotizacion)
    {
        $this->verificarPropiedad($cotizacion);

        $usuario = $request->user();
        $esAdmin = (int) $usuario->id_rol === 1;

        $notaDescuento = '';

        // NIT del cliente: se consulta al SIN antes de crear la factura (fuera de la transacción).
        $excepcionNit = false;
        $clienteNit = $cotizacion->cliente()->with('tipoDocumento')->first();

        if ($this->esNit($clienteNit)) {
            $verificacion = $this->nit->verificar((string) $clienteNit->documento_identidad);

            if ($verificacion['estado'] === SinNitService::INVALIDO) {
                if (! $request->boolean('confirmar_excepcion')) {
                    throw ValidationException::withMessages([
                        'confirmar_excepcion' => "El SIN no reconoce el NIT {$clienteNit->documento_identidad} ({$verificacion['mensaje']}). "
                            . 'Si el cliente confirma que es su NIT, marca la casilla para emitir con excepción de NIT; si no, corrige el documento en la ficha del cliente.',
                    ]);
                }

                $excepcionNit = true;
            }
        }

        [$factura, $faltantes] = DB::transaction(function () use ($request, $cotizacion, $usuario, $esAdmin, $excepcionNit, &$notaDescuento) {
            $bloqueada = Cotizacion::whereKey($cotizacion->getKey())->lockForUpdate()->firstOrFail();

            if ($motivo = $this->motivoBloqueo($bloqueada)) {
                throw ValidationException::withMessages(['items' => $motivo]);
            }

            $items = $this->resolverItems($bloqueada, $request->input('items'));

            // Un ítem con total 0 (precio 0 o descuento del 100 %) no es facturable.
            foreach ($items as $item) {
                if ((float) $item->subtotal <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'El ítem «' . $item->descripcion . '» tiene un total de Bs 0 (por ejemplo, un descuento del 100 %). No se puede facturar.',
                    ]);
                }
            }

            $totalItems = round($items->sum('subtotal'), 2);

            $descuentoItems = round((float) $items->sum('descuento'), 2);
            $heredable = $this->descuentoHeredado($bloqueada, $totalItems);

            // Si la cotización ya trae descuento (en los ítems o general), no se descuenta una segunda vez al facturar.
            $heredado = ! $request->filled('descuento_adicional');

            if (! $heredado && ($descuentoItems > 0 || $heredable > 0)) {
                throw ValidationException::withMessages([
                    'descuento_adicional' => 'Esta cotización ya tiene descuento, por eso no se puede dar otro al facturar. '
                        . 'Si el cliente merece más descuento, modifica la cotización antes de facturar.',
                ]);
            }

            // Sin descuento escrito, la factura hereda la parte que le toca del descuento general de la cotización.
            $pedido = $heredado
                ? $heredable
                : $this->descuentoAdicional($request->input('descuento_adicional'), $totalItems);
            $descuento = $this->descuentoFacturable($pedido, $totalItems);

            if ($descuento !== $pedido) {
                $notaDescuento = ' El descuento adicional se ajustó de Bs ' . number_format($pedido, 2)
                    . ' a Bs ' . number_format($descuento, 2) . ' para que el total sea facturable al centavo en el SIN.';
            }

            if ($heredado && $descuento > 0) {
                $notaDescuento = ' Se aplicó el descuento general de la cotización (Bs ' . number_format($descuento, 2) . ').' . $notaDescuento;
            }

            // Recordatorio para el cliente: todo el descuento que lleva esta factura.
            if ($descuentoItems > 0 || $descuento > 0) {
                $notaDescuento .= ' DESCUENTO APLICADO: Bs ' . number_format($descuentoItems + $descuento, 2)
                    . ($descuentoItems > 0 && $descuento > 0
                        ? ' (ítems Bs ' . number_format($descuentoItems, 2) . ' + adicional Bs ' . number_format($descuento, 2) . ')'
                        : '')
                    . '. Recuérdaselo al cliente.';
            }

            $totalFinal = round($totalItems - $descuento, 2);

            if ($totalFinal < 0.10) {
                throw ValidationException::withMessages([
                    'descuento_adicional' => 'El descuento adicional deja la factura prácticamente en cero. No se permite descontar el 100 %.',
                ]);
            }

            // El total a facturar debe poder emitirse al centavo con la v3 del SIN.
            if (! TotalFacturable::esAlcanzable($totalFinal)) {
                throw ValidationException::withMessages([
                    'items' => 'El total de la factura (Bs ' . number_format($totalFinal, 2)
                        . ') no se puede facturar exacto con el SIN: quedaría con 1 centavo de diferencia. '
                        . 'Cambia la combinación de ítems o el descuento adicional (por ejemplo, agrega o quita un ítem).',
                ]);
            }

            $cliente = $bloqueada->cliente()->with(['tipoDocumento', 'correos'])->first();

            $factura = Factura::create([
                'numero'                    => $esAdmin ? $this->siguienteNumero() : null,
                'id_cotizacion'             => $bloqueada->id_cotizacion,
                'id_usuario'                => $usuario->id_usuario,
                'fecha_emision'             => now(),
                // Datos del cliente congelados
                'cliente_razon_social'      => $this->razonSocialFactura($cliente),
                'cliente_id_tipo_documento' => $cliente->tipoDocumento?->getKey(),
                'cliente_documento'         => $cliente->documento_identidad,
                'cliente_complemento'       => $cliente->complemento,
                'cliente_correo'            => $cliente->correos->first()?->correo,
                'monto_total'               => $totalFinal,
                'descuento_adicional'       => $descuento,
                'forma_pago'                => $request->input('forma_pago'),
                'numero_tarjeta'            => $this->numeroTarjeta($request),
                'codigo_excepcion'          => $excepcionNit,
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
            ->with($exito ? 'success' : 'error', $mensaje . $notaDescuento . $this->stock->mensajeFaltantes($faltantes))
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

    /** Descarga el XML que se envió al SIN (es el que acompaña al PDF en el correo al cliente). */
    public function xml(Factura $factura)
    {
        $this->verificarPropiedad($factura->cotizacion);

        abort_unless(filled($factura->xml), 404, 'Esta factura no tiene XML guardado.');

        return response($factura->xml, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="factura-' . $factura->numero . '.xml"',
        ]);
    }

    /** Envía al cliente, por correo, el PDF y el XML de una factura validada por el SIN. */
    public function enviarCorreo(Request $request, Factura $factura, FacturaPdfService $servicio)
    {
        $this->verificarPropiedad($factura->cotizacion);

        if ($factura->estado !== Factura::APROBADA || $factura->estado_sin !== Factura::SIN_VALIDADA) {
            return back()->with('error', 'Solo se envía por correo una factura aprobada y validada por el SIN.');
        }

        if (blank($factura->xml)) {
            return back()->with('error', 'Esta factura no tiene el XML guardado (se emitió antes de esta función), por eso no se puede enviar por correo.');
        }

        $factura->load('cotizacion.cliente.correos');

        $correos = $factura->cotizacion->cliente->correos
            ->pluck('correo')
            ->push($factura->cliente_correo)
            ->filter(fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL))
            ->map(fn ($c) => mb_strtolower(trim($c)))
            ->unique()
            ->values()
            ->all();

        if (empty($correos)) {
            return back()->with('error', 'El cliente no tiene un correo válido registrado.');
        }

        try {
            $pdf = $servicio->generar($factura)->output();
            Mail::to($correos)->send(new FacturaEnviada($factura, $pdf, (string) $factura->xml));
        } catch (Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'No se pudo enviar el correo. Revisa la configuración de correo e inténtalo de nuevo.'
                . (config('app.debug') ? ' (' . $e->getMessage() . ')' : '')
            );
        }

        $factura->forceFill([
            'fecha_correo'     => now(),
            'correo_enviado_a' => mb_substr(implode(', ', $correos), 0, 500),
        ])->saveQuietly();

        return back()->with('success', 'Factura enviada con PDF y XML a: ' . implode(', ', $correos) . '.');
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

        // El SIN no deja anular de nuevo una factura cuya anulación ya se revirtió (error 981).
        if ($validadaPorSin && (int) $factura->reversiones >= 1) {
            return back()->with('error', 'Esta factura ya tuvo una reversión de anulación: el SIN no permite anularla otra vez.');
        }

        if ($validadaPorSin) {
            $request->validate(['motivo_sin' => ['required', 'in:1,3,4']]);

            try {
                [$aceptada, $codigoEstado, $mensaje] = $anulacion->anularFactura($factura, (int) $request->motivo_sin);
            } catch (Throwable $e) {
                Log::warning("SIN: no se pudo anular la factura {$factura->getKey()}: " . $e->getMessage());

                return back()->with('error', 'No se pudo anular en el SIN: ' . $e->getMessage());
            }

            if (! $aceptada) {
                $explicacion = str_contains((string) $mensaje, '981')
                    ? 'Esta factura ya no admite anulación en el SIN (por ejemplo, porque su anulación ya fue revertida una vez).'
                    : $mensaje;

                return back()->with('error', 'El SIN no aceptó la anulación: ' . $explicacion);
            }

            $datosSin = [
                'codigo_estado'    => $codigoEstado,
                'mensaje_sin'      => 'ANULADA EN EL SIN (motivo ' . $request->motivo_sin . '). ' . $mensaje,
                'motivo_anulacion' => (int) $request->motivo_sin,
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

    /**
     * Revierte la anulación de una factura anulada en el SIN (una sola vez). Vuelve a estar aprobada:
     * se reservan otra vez sus ítems y se descuenta el stock.
     */
    public function revertirAnulacion(Request $request, Factura $factura, SinFacturaAnulacionService $anulacion)
    {
        abort_unless((int) $request->user()->id_rol === 1, 403);

        if ($factura->estado !== Factura::ANULADA || $factura->estado_sin !== Factura::SIN_VALIDADA) {
            return back()->with('error', 'Solo se revierte la anulación de una factura que fue anulada en el SIN.');
        }

        if ((int) $factura->reversiones >= 1) {
            return back()->with('error', 'Esta factura ya tuvo su reversión; el SIN no permite una segunda.');
        }

        $factura->load(['cotizacion', 'detalles.recibos', 'detalles.facturas']);

        if ($factura->cotizacion->estado === Cotizacion::ANULADA) {
            return back()->with('error', 'La cotización de esta factura está anulada: no se puede revertir.');
        }

        // Al anular, sus ítems quedaron libres: si ya se usaron en otro recibo o factura, no se puede revertir.
        $ocupados = $factura->detalles->filter(function ($d) use ($factura) {
            return $d->recibos->where('estado', '!=', Recibo::ANULADO)->isNotEmpty()
                || $d->facturas->where('estado', '!=', Factura::ANULADA)->where('id_factura', '!=', $factura->id_factura)->isNotEmpty();
        });

        if ($ocupados->isNotEmpty()) {
            return back()->with('error', 'No se puede revertir: estos ítems ya están en otro recibo o factura: '
                . $ocupados->pluck('descripcion')->join(', ') . '.');
        }

        try {
            [$aceptada, $codigoEstado, $mensaje] = $anulacion->revertirFactura($factura);
        } catch (Throwable $e) {
            Log::warning("SIN: no se pudo revertir la anulación de la factura {$factura->getKey()}: " . $e->getMessage());

            return back()->with('error', 'No se pudo revertir en el SIN: ' . $e->getMessage());
        }

        if (! $aceptada) {
            return back()->with('error', 'El SIN no aceptó la reversión: ' . $mensaje);
        }

        $idUsuario = $request->user()->id_usuario;

        $faltantes = DB::transaction(function () use ($factura, $idUsuario, $codigoEstado, $mensaje) {
            $actual = Factura::whereKey($factura->getKey())->lockForUpdate()->firstOrFail();

            $actual->update([
                'estado'           => Factura::APROBADA,
                'reversiones'      => (int) $actual->reversiones + 1,
                'motivo_anulacion' => null,
                'codigo_estado'    => $codigoEstado,
                'mensaje_sin'      => 'ANULACIÓN REVERTIDA EN EL SIN. ' . $mensaje,
            ]);

            return $this->stock->descontar(StockService::DOC_FACTURA, $actual->id_factura, $actual->detalles, $idUsuario);
        });

        return back()->with('success', 'Anulación revertida: la factura está nuevamente vigente (el SIN no permite una segunda reversión).'
            . $this->stock->mensajeFaltantes($faltantes));
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

        if ($enviada->estado_sin === Factura::SIN_FUERA_DE_LINEA) {
            return [true, 'No hay comunicación con el SIN: la factura se emitió FUERA DE LÍNEA. Entrégala al cliente y se enviará al SIN cuando se recupere la conexión.'];
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

    /** Razón social de la factura: con los NIT especiales 99002 y 99003 el SIN exige un nombre fijo. */
    private function razonSocialFactura(\App\Modules\Clientes\Models\Cliente $cliente): string
    {
        if ($this->esNit($cliente)) {
            $fija = SinNitService::razonSocialEspecial((string) $cliente->documento_identidad);

            if ($fija) {
                return $fija;
            }
        }

        return (string) $cliente->razon_social;
    }

    /** ¿El documento del cliente es de tipo NIT (código 5 del SIN)? */
    private function esNit(?\App\Modules\Clientes\Models\Cliente $cliente): bool
    {
        return $cliente !== null && (int) $cliente->tipoDocumento?->codigo_sin === 5;
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

    /** Parte del descuento general de la cotización que le toca a estos ítems (proporcional a su importe). */
    private function descuentoHeredado(Cotizacion $cotizacion, float $totalItemsFacturados): float
    {
        $general = (float) $cotizacion->descuento_general;

        if ($general <= 0) {
            return 0.0;
        }

        $todos = (float) $cotizacion->detalles()->sum('subtotal');

        return $todos > 0 ? round($general * $totalItemsFacturados / $todos, 2) : 0.0;
    }

    /** Descuento global en Bs: acepta un monto («5», «8.99») o un porcentaje («10%») del total. */
    private function descuentoAdicional(?string $texto, float $total): float
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return 0.0;
        }

        $esPorcentaje = str_ends_with($texto, '%');
        $numero = (float) str_replace(',', '.', trim(rtrim($texto, '% ')));

        if ($esPorcentaje) {
            if ($numero >= 100) {
                throw ValidationException::withMessages(['descuento_adicional' => 'No se puede descontar el 100 % de la factura.']);
            }

            return round($total * $numero / 100, 2);
        }

        if ($numero >= $total) {
            throw ValidationException::withMessages(['descuento_adicional' => 'El descuento adicional debe ser menor que el total de la factura.']);
        }

        return round($numero, 2);
    }

    /**
     * Si con el descuento pedido el total no se puede facturar al centavo (el SIN exige IVA = 13 % exacto
     * del neto), lo corrige en 1 a 5 centavos, prefiriendo el descuento mayor (el cliente nunca paga de más).
     */
    private function descuentoFacturable(float $descuento, float $totalItems): float
    {
        if ($descuento <= 0 || TotalFacturable::esAlcanzable(round($totalItems - $descuento, 2))) {
            return $descuento;
        }

        foreach ([0.01, -0.01, 0.02, -0.02, 0.03, -0.03, 0.04, -0.04, 0.05, -0.05] as $delta) {
            $candidato = round($descuento + $delta, 2);

            if ($candidato > 0 && $candidato < $totalItems && TotalFacturable::esAlcanzable(round($totalItems - $candidato, 2))) {
                return $candidato;
            }
        }

        return $descuento;
    }

    /** Número que va al SIN: 4 primeros dígitos + 8 ceros + 4 últimos. Sin tarjeta, nulo. */
    private function numeroTarjeta(Request $request): ?string
    {
        if (! in_array($request->input('forma_pago'), Factura::FORMAS_CON_TARJETA, true)) {
            return null;
        }

        return $request->input('tarjeta_inicio') . '00000000' . $request->input('tarjeta_fin');
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
