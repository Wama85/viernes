<?php

namespace App\Modules\Productos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Http\Requests\ProductoRequest;
use App\Modules\Productos\Models\Categoria;
use App\Modules\Productos\Models\Producto;
use App\Modules\Productos\Models\ProductoSin;
use App\Modules\Productos\Models\UnidadMedida;
use App\Modules\Productos\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(Request $request)
    {
        $query = Producto::with(['categoria', 'unidadMedida', 'productoSin']);

        if ($request->filled('categoria')) {
            $query->where('id_categoria', $request->categoria);
        }

        if ($request->filled('buscar')) {
            $texto = trim($request->buscar);

            $query->where(function ($q) use ($texto) {
                $q->where('descripcion_producto', 'like', "%{$texto}%")
                  ->orWhere('detalle', 'like', "%{$texto}%");

                if (is_numeric($texto)) {
                    $q->orWhere('id_producto', $texto);
                }
            });
        }

        $productos = $query->orderBy('descripcion_producto')->paginate(15)->withQueryString();
        $categorias = Categoria::where('categoria_estado', 1)->orderBy('nombre_categoria')->get();

        return view('productos.index', compact('productos', 'categorias'));
    }

    public function create()
    {
        return view('productos.create', $this->datosFormulario());
    }

    public function store(ProductoRequest $request)
    {
        $datos = $this->prepararDatos($request);
        $stockInicial = $datos['controla_stock'] ? (float) ($request->input('stock') ?? 0) : 0;

        DB::transaction(function () use ($datos, $stockInicial) {
            // El producto nace con stock 0; el stock inicial entra como un movimiento registrado.
            $producto = Producto::create($datos + ['producto_estado' => 1, 'stock' => 0]);

            if ($stockInicial > 0) {
                $this->stock->stockInicial($producto, $stockInicial, Auth::id());
            }
        });

        return redirect()->route('productos.index')
            ->with('success', 'Producto registrado correctamente.');
    }

    public function edit(Producto $producto)
    {
        return view('productos.edit', ['producto' => $producto] + $this->datosFormulario($producto));
    }

    public function update(ProductoRequest $request, Producto $producto)
    {
        // El stock no se toca aquí: cambia solo con movimientos.
        $producto->update($this->prepararDatos($request, $producto));

        return redirect()->route('productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function cambiarEstado(Producto $producto)
    {
        $producto->producto_estado = $producto->producto_estado == 1 ? 0 : 1;
        $producto->save();

        $mensaje = $producto->producto_estado == 1
            ? 'Producto reactivado correctamente.'
            : 'Producto dado de baja correctamente.';

        return redirect()->route('productos.index')->with('success', $mensaje);
    }

    private function datosFormulario(?Producto $producto = null): array
    {
        // Fila del SIN que debe conservarse tal cual (la elegida antes de un error de validación,
        // o la que ya tiene el producto que se está editando).
        $idElegido = (int) (old('id_producto_sin') ?: ($producto->id_producto_sin ?? 0));

        return [
            'opcionesSin' => $this->opcionesProductoSin($idElegido),
            'categorias' => Categoria::where('categoria_estado', 1)
                ->orderBy('nombre_categoria')->get(),
            'unidades' => UnidadMedida::where('unidad_medida_estado', 1)
                ->orderBy('nombre_unidad_medida')->get(),
            'unidadPorDefecto' => UnidadMedida::where('nombre_unidad_medida', 'PIEZAS')
                ->value('id_unidad_medida'),
        ];
    }

    /**
     * Lista de productos del SIN para el buscador, sin repetidos.
     * Un mismo código de producto puede venir en varias actividades; aquí se muestra una sola vez,
     * quedándose con la fila elegida, o con la que más usas en tus productos, o con la primera.
     * Orden final: la elegida, luego las más usadas, luego el resto por código.
     */
    private function opcionesProductoSin(int $idElegido): array
    {
        $usos = Producto::select('id_producto_sin', DB::raw('COUNT(*) as total'))
            ->groupBy('id_producto_sin')
            ->pluck('total', 'id_producto_sin');

        return ProductoSin::where('producto_sin_estado', 1)
            ->get(['id_producto_sin', 'codigo_producto_sin', 'codigo_actividad', 'descripcion_producto_sin'])
            ->sortBy([
                fn ($a, $b) => (int) ((int) $b->id_producto_sin === $idElegido) <=> (int) ((int) $a->id_producto_sin === $idElegido),
                fn ($a, $b) => ($usos[$b->id_producto_sin] ?? 0) <=> ($usos[$a->id_producto_sin] ?? 0),
                ['codigo_producto_sin', 'asc'],
                ['codigo_actividad', 'asc'],
            ])
            ->unique('codigo_producto_sin')
            ->map(fn ($ps) => [
                'id' => $ps->id_producto_sin,
                'codigo' => $ps->codigo_producto_sin,
                'actividad' => $ps->codigo_actividad,
                'nombre' => $ps->descripcion_producto_sin,
                'usado' => ($usos[$ps->id_producto_sin] ?? 0) > 0,
            ])
            ->values()
            ->all();
    }

    private function prepararDatos(ProductoRequest $request, ?Producto $producto = null): array
    {
        $datos = $request->validated();

        $datos['controla_stock'] = $request->boolean('controla_stock');
        $datos['stock_minimo'] = $datos['stock_minimo'] ?? 0;
        $datos['costo_compra'] = $datos['costo_compra'] ?? 0;

        // El stock nunca se guarda directo desde el formulario.
        unset($datos['stock']);

        if ($request->hasFile('imagen')) {
            if ($producto && $producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }
            $datos['imagen'] = $request->file('imagen')->store('productos', 'public');
        } else {
            unset($datos['imagen']);
        }

        return $datos;
    }
}
