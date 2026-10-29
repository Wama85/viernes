<?php

namespace App\Modules\Cotizaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Cotizaciones\Http\Requests\CotizacionRequest;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Productos\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\TotalFacturable;

class CotizacionController extends Controller
{
    /** Importe máximo de una línea y del total (columnas decimal(10,2)). */
    private const IMPORTE_MAXIMO = 99999999.99;

    public function index(Request $request)
    {
        $cotizaciones = Cotizacion::with(['cliente', 'creador'])
            ->when(! $this->esAdmin(), fn ($q) => $q->where('id_usuario', Auth::id()))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('anio'), fn ($q) => $q->where('anio', $request->anio))
            ->orderByDesc('anio')
            ->orderByDesc('numero')
            ->paginate(15)
            ->withQueryString();

        $anios = Cotizacion::select('anio')->distinct()->orderByDesc('anio')->pluck('anio');

        return view('cotizaciones.index', compact('cotizaciones', 'anios'));
    }

    public function create()
    {
        return view('cotizaciones.create', $this->datosFormulario());
    }

    public function store(CotizacionRequest $request)
    {
        // Primero se calculan las líneas y el total; si algo falla, no se toca la base.
        [$filas, $total] = $this->prepararDetalles($request->items);

        // Stock insuficiente: se avisa y se pide confirmación (no bloquea).
        if ($aviso = $this->advertirStock($request, $filas)) {
            return $aviso;
        }

        $esAdmin = $this->esAdmin();

        $cotizacion = DB::transaction(function () use ($request, $filas, $total, $esAdmin) {
            $anio = now()->year;
            $numero = (Cotizacion::where('anio', $anio)->lockForUpdate()->max('numero') ?? 0) + 1;

            $cotizacion = Cotizacion::create([
                'anio'              => $anio,
                'numero'            => $numero,
                'id_cliente'        => $request->id_cliente,
                'id_usuario'        => Auth::id(),
                'fecha_emision'     => now(),
                'fecha_vencimiento' => $this->calcularVencimiento($request),
                'estado'            => $esAdmin ? Cotizacion::APROBADA : Cotizacion::PENDIENTE,
                'id_aprobador'      => $esAdmin ? Auth::id() : null,
                'fecha_aprobacion'  => $esAdmin ? now() : null,
                'observaciones'     => $request->observaciones,
                'total'             => $total,
            ]);

            $cotizacion->detalles()->createMany($filas);

            return $cotizacion;
        });

        return redirect()->route('cotizaciones.show', $cotizacion)
            ->with('success', "Cotización {$cotizacion->numero_completo} registrada.");
    }

    public function show(Cotizacion $cotizacion)
    {
        $this->verificarPropiedad($cotizacion);

        $cotizacion->load([
            'cliente.tipoDocumento', 'cliente.correos', 'cliente.telefonos',
            'detalles.unidadMedida', 'detalles.recibos', 'detalles.facturas',
            'creador', 'aprobador',
        ]);

        return view('cotizaciones.show', compact('cotizacion'));
    }

    public function edit(Cotizacion $cotizacion)
    {
        $this->verificarPropiedad($cotizacion);

        if (! $cotizacion->puedeEditarPor(Auth::user())) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Esta cotización ya no se puede modificar.');
        }

        $cotizacion->load('detalles.unidadMedida');

        return view('cotizaciones.edit', array_merge(
            $this->datosFormulario($cotizacion),
            compact('cotizacion')
        ));
    }

    public function update(CotizacionRequest $request, Cotizacion $cotizacion)
    {
        $this->verificarPropiedad($cotizacion);

        if (! $cotizacion->puedeEditarPor(Auth::user())) {
            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('error', 'Esta cotización ya no se puede modificar.');
        }

        // Con recibos activos los ítems no se tocan: solo cliente, vigencia y observaciones.
        $itemsBloqueados = $cotizacion->tieneRecibos();
        $filas = [];
        $total = (float) $cotizacion->total;

        if (! $itemsBloqueados) {
            [$filas, $total] = $this->prepararDetalles($request->items);

            if ($aviso = $this->advertirStock($request, $filas)) {
                return $aviso;
            }
        }

        DB::transaction(function () use ($request, $cotizacion, $filas, $total, $itemsBloqueados) {
            $datos = [
                'id_cliente'    => $request->id_cliente,
                'observaciones' => $request->observaciones,
            ];

            if (! $itemsBloqueados) {
                $datos['total'] = $total;
                // Si cambian los ítems, el cliente debe recibir la cotización de nuevo.
                $datos['fecha_envio'] = null;
            }

            if ($request->filled('vigencia_cantidad')) {
                $datos['fecha_vencimiento'] = $this->calcularVencimiento($request);
            }

            // Un Vendedor que modifica vuelve a necesitar la aprobación del Administrador.
            if (! $this->esAdmin()) {
                $datos['estado'] = Cotizacion::PENDIENTE;
                $datos['id_aprobador'] = null;
                $datos['fecha_aprobacion'] = null;
            }

            $cotizacion->forceFill($datos)->save();

            if (! $itemsBloqueados) {
                // Las líneas se reemplazan completas (sin recibos que dependan de ellas).
                $cotizacion->detalles()->delete();
                $cotizacion->detalles()->createMany($filas);
            }
        });

        return redirect()->route('cotizaciones.show', $cotizacion)
            ->with('success', "Cotización {$cotizacion->numero_completo} actualizada.");
    }

    public function aprobar(Cotizacion $cotizacion)
    {
        if ($cotizacion->estado !== Cotizacion::PENDIENTE) {
            return back()->with('error', 'Solo se pueden aprobar cotizaciones pendientes.');
        }

        $cotizacion->update([
            'estado'           => Cotizacion::APROBADA,
            'id_aprobador'     => Auth::id(),
            'fecha_aprobacion' => now(),
        ]);

        return back()->with('success', "Cotización {$cotizacion->numero_completo} aprobada.");
    }

    public function anular(Cotizacion $cotizacion)
    {
        if (in_array($cotizacion->estado, [Cotizacion::FACTURADA, Cotizacion::ANULADA])) {
            return back()->with('error', 'Esta cotización no se puede anular.');
        }

        if ($cotizacion->tieneRecibos() || $cotizacion->tieneFacturas()) {
            return back()->with('error', 'Primero debe anular los recibos y facturas de esta cotización.');
        }

        $cotizacion->update(['estado' => Cotizacion::ANULADA]);

        return back()->with('success', "Cotización {$cotizacion->numero_completo} anulada.");
    }

    // ------------------------------------------------------------------

    private function esAdmin(): bool
    {
        return Auth::user()->id_rol == 1;
    }

    /** El Vendedor solo accede a sus propias cotizaciones. */
    private function verificarPropiedad(Cotizacion $cotizacion): void
    {
        if (! $this->esAdmin() && $cotizacion->id_usuario !== Auth::id()) {
            abort(403);
        }
    }

    private function calcularVencimiento(Request $request)
    {
        $cantidad = (int) $request->vigencia_cantidad;

        return $request->vigencia_unidad === 'HORAS'
            ? now()->addHours($cantidad)
            : now()->addDays($cantidad);
    }

    /**
     * Al editar, el cliente y los productos que la cotización ya usa se conservan
     * en las listas aunque hayan sido dados de baja.
     */
    private function datosFormulario(?Cotizacion $cotizacion = null): array
    {
        $idsProductos = $cotizacion ? $cotizacion->detalles->pluck('id_producto')->all() : [];

        return [
            'clientes' => Cliente::where('cliente_estado', 1)
                ->when($cotizacion, fn ($q) => $q->orWhere('id_cliente', $cotizacion->id_cliente))
                ->orderBy('razon_social')
                ->get(['id_cliente', 'documento_identidad', 'complemento', 'razon_social']),
            'productos' => Producto::with('unidadMedida')
                ->where('producto_estado', 1)
                ->when($idsProductos, fn ($q) => $q->orWhereIn('id_producto', $idsProductos))
                ->orderBy('descripcion_producto')
                ->get(),
        ];
    }

    /**
     * Si algún producto con control de stock no alcanza, vuelve al formulario con un aviso.
     * El usuario puede continuar de todas formas (confirmar_stock).
     */
    private function advertirStock(Request $request, array $filas): ?RedirectResponse
    {
        if ($request->boolean('confirmar_stock')) {
            return null;
        }

        $faltantes = $this->stockInsuficiente($filas);

        if (empty($faltantes)) {
            return null;
        }

        return back()->withInput()->with('advertencia_stock', $faltantes);
    }

    private function stockInsuficiente(array $filas): array
    {
        $pedidos = collect($filas)
            ->groupBy('id_producto')
            ->map(fn ($lineas) => (float) $lineas->sum('cantidad'));

        $faltantes = [];

        $productos = Producto::whereIn('id_producto', $pedidos->keys())
            ->where('controla_stock', true)
            ->get();

        foreach ($productos as $producto) {
            $pedido = $pedidos[$producto->id_producto];
            $disponible = (float) $producto->stock;

            if ($pedido > $disponible) {
                $faltantes[] = [
                    'producto'   => $producto->descripcion_producto,
                    'pedido'     => $pedido,
                    'disponible' => $disponible,
                ];
            }
        }

        return $faltantes;
    }

    /**
     * Arma las líneas a guardar y el total. La descripción y la unidad se copian
     * del producto en el servidor; el precio y el descuento vienen del formulario.
     * El total debe poder facturarse al centavo con la v3 del SIN.
     */
    private function prepararDetalles(array $items): array
    {
        $productos = Producto::whereIn('id_producto', collect($items)->pluck('id_producto'))
            ->get()
            ->keyBy('id_producto');

        $filas = [];
        $total = 0;

        foreach (array_values($items) as $i => $item) {
            $producto = $productos[$item['id_producto']];
            $cantidad = (float) $item['cantidad'];
            $precio = (float) $item['precio_unitario'];
            $descuento = (float) ($item['descuento'] ?? 0);
            $bruto = round($cantidad * $precio, 2);

            if ($bruto > self::IMPORTE_MAXIMO) {
                throw ValidationException::withMessages([
                    "items.$i.cantidad" => 'El importe de la línea (cantidad × precio) supera el máximo permitido.',
                ]);
            }

            if ($descuento > $bruto) {
                throw ValidationException::withMessages([
                    "items.$i.descuento" => 'El descuento no puede ser mayor al importe de la línea.',
                ]);
            }

            $subtotal = round($bruto - $descuento, 2);
            $total += $subtotal;

            $filas[] = [
                'id_producto'           => $producto->id_producto,
                'id_unidad_medida'      => $producto->id_unidad_medida,
                'descripcion'           => $producto->descripcion_producto,
                'descripcion_adicional' => filled($item['descripcion_adicional'] ?? null)
                    ? mb_strtoupper($item['descripcion_adicional'])
                    : null,
                'cantidad'              => $cantidad,
                'precio_unitario'       => $precio,
                'descuento'             => $descuento,
                'subtotal'              => $subtotal,
            ];
        }

        $total = round($total, 2);

        if ($total > self::IMPORTE_MAXIMO) {
            throw ValidationException::withMessages([
                'items' => 'El total de la cotización supera el máximo permitido (99,999,999.99).',
            ]);
        }

        if (! TotalFacturable::esAlcanzable($total)) {
            [$menor, $mayor] = TotalFacturable::enterosCercanos($total);
            $sugeridos = collect([$menor, $mayor])
                ->filter()
                ->map(fn ($v) => 'Bs ' . number_format($v, 2))
                ->implode(' o ');

            throw ValidationException::withMessages([
                'items' => 'El total Bs ' . number_format($total, 2)
                    . ' no se puede facturar exacto con el SIN (quedaría con 1 centavo de diferencia). '
                    . 'Ajusta un precio para que el total sea, por ejemplo, ' . $sugeridos . '.',
            ]);
        }

        return [$filas, $total];
    }
}
