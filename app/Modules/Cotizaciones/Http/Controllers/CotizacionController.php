<?php

namespace App\Modules\Cotizaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Cotizaciones\Http\Requests\CotizacionRequest;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Productos\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CotizacionController extends Controller
{
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
            'detalles.unidadMedida', 'creador', 'aprobador',
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

        $cotizacion->load('detalles');

        return view('cotizaciones.edit', array_merge(
            $this->datosFormulario(),
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

        [$filas, $total] = $this->prepararDetalles($request->items);
        $recibido = $cotizacion->montoRecibido();
if ($total < $recibido) {
    throw ValidationException::withMessages([
        'items' => 'El total no puede ser menor a lo ya recibido (Bs ' . number_format($recibido, 2) . ').',
    ]);
}

        DB::transaction(function () use ($request, $cotizacion, $filas, $total) {
            $datos = [
                'id_cliente'    => $request->id_cliente,
                'observaciones' => $request->observaciones,
                'total'         => $total,
            ];

            if ($request->filled('vigencia_cantidad')) {
                $datos['fecha_vencimiento'] = $this->calcularVencimiento($request);
            }

            // Un Vendedor que modifica vuelve a necesitar la aprobación del Administrador.
            if (! $this->esAdmin()) {
                $datos['estado'] = Cotizacion::PENDIENTE;
                $datos['id_aprobador'] = null;
                $datos['fecha_aprobacion'] = null;
            }

            $cotizacion->update($datos);

            // Las líneas se reemplazan completas.
            $cotizacion->detalles()->delete();
            $cotizacion->detalles()->createMany($filas);
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
if ($cotizacion->tieneRecibos()) {
    return back()->with('error', 'Primero debe anular los recibos de esta cotización.');
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

    private function datosFormulario(): array
    {
        return [
            'clientes' => Cliente::where('cliente_estado', 1)
                ->orderBy('razon_social')
                ->get(['id_cliente', 'documento_identidad', 'complemento', 'razon_social']),
            'productos' => Producto::with('unidadMedida')
                ->where('producto_estado', 1)
                ->orderBy('descripcion_producto')
                ->get(),
        ];
    }

    /**
     * Arma las líneas a guardar y el total. La descripción y la unidad se copian
     * del producto en el servidor; el precio y el descuento vienen del formulario.
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

        return [$filas, round($total, 2)];
    }
}
