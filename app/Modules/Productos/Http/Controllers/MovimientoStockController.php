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

        return view('productos.movimientos', compact('producto', 'movimientos'));
    }

    public function store(Request $request, Producto $producto)
    {
        if (! $producto->controla_stock) {
            return back()->with('error', 'Este producto no controla stock.');
        }

        $datos = $request->validate([
            'tipo'     => ['required', Rule::in(['ENTRADA', 'AJUSTE'])],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'motivo'   => ['nullable', 'string', 'max:255'],
        ], [
            'cantidad.required' => 'Indica la cantidad.',
            'cantidad.min'      => 'La cantidad no puede ser negativa.',
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
}
