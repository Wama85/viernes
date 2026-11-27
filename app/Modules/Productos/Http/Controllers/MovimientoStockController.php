<?php

namespace App\Modules\Productos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Models\Producto;
use App\Modules\Productos\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovimientoStockController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(Producto $producto)
    {
        $movimientos = $producto->movimientos()
            ->with('usuario')
            ->orderByDesc('id_movimiento_stock')
            ->paginate(20);

        return view('productos.movimientos', [
            'producto'        => $producto,
            'movimientos'     => $movimientos,
            'admiteDecimales' => $this->admiteDecimales($producto),
        ]);
    }

    public function store(Request $request, Producto $producto)
    {
        if (! $producto->controla_stock) {
            return back()->with('error', 'Este producto no controla stock.');
        }

        // Piezas, cajas, etc. solo admiten cantidades enteras; los decimales se reservan a unidades de peso, volumen o longitud.
        $admiteDecimales = $this->admiteDecimales($producto);

        $datos = $request->validate([
            'tipo'     => ['required', Rule::in(['ENTRADA', 'AJUSTE'])],
            'cantidad' => $admiteDecimales
                ? ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/']
                : ['required', 'integer', 'min:0'],
            'motivo'   => ['nullable', 'string', 'max:255'],
        ], [
            'cantidad.required' => 'Indica la cantidad.',
            'cantidad.min'      => 'La cantidad no puede ser negativa.',
            'cantidad.integer'  => 'Este producto se cuenta en unidades enteras: escribe un número sin decimales.',
            'cantidad.regex'    => 'La cantidad admite hasta 2 decimales.',
        ]);

        $cantidad = (float) $datos['cantidad'];
        $motivo = filled($datos['motivo'] ?? null) ? mb_strtoupper(trim($datos['motivo'])) : null;

        if ($datos['tipo'] === 'ENTRADA' && $cantidad <= 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad que ingresa debe ser mayor a cero.']);
        }

        if ($datos['tipo'] === 'AJUSTE' && ! $motivo) {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo del ajuste.']);
        }

        DB::transaction(function () use ($datos, $producto, $cantidad, $motivo) {
            if ($datos['tipo'] === 'ENTRADA') {
                $this->stock->entrada($producto, $cantidad, Auth::id(), $motivo);
            } else {
                $this->stock->ajustar($producto, $cantidad, Auth::id(), $motivo);
            }
        });

        return redirect()->route('productos.movimientos', $producto)
            ->with('success', 'Movimiento registrado.');
    }

    /** Unidades que sí se miden con decimales (peso, volumen, longitud). El resto se cuenta en enteros. */
    private function admiteDecimales(Producto $producto): bool
    {
        $unidad = mb_strtoupper((string) $producto->unidadMedida?->nombre_unidad_medida);

        foreach (['KILO', 'GRAMO', 'LIBRA', 'ONZA', 'TONELADA', 'LITRO', 'GALON', 'METRO', 'CENTIMETRO', 'PULGADA', 'PIE'] as $clave) {
            if (str_contains($unidad, $clave)) {
                return true;
            }
        }

        return false;
    }
}
