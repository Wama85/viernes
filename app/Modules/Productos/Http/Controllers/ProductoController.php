<?php

namespace App\Modules\Productos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Http\Requests\ProductoRequest;
use App\Modules\Productos\Models\Categoria;
use App\Modules\Productos\Models\Producto;
use App\Modules\Productos\Models\ProductoSin;
use App\Modules\Productos\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
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
        Producto::create($this->prepararDatos($request) + ['producto_estado' => 1]);

        return redirect()->route('productos.index')
            ->with('success', 'Producto registrado correctamente.');
    }

    public function edit(Producto $producto)
    {
        return view('productos.edit', ['producto' => $producto] + $this->datosFormulario());
    }

    public function update(ProductoRequest $request, Producto $producto)
    {
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

    private function datosFormulario(): array
    {
        return [
            'productosSin' => ProductoSin::where('producto_sin_estado', 1)
                ->orderBy('codigo_producto_sin')->get(),
            'categorias' => Categoria::where('categoria_estado', 1)
                ->orderBy('nombre_categoria')->get(),
            'unidades' => UnidadMedida::where('unidad_medida_estado', 1)
                ->orderBy('nombre_unidad_medida')->get(),
            'unidadPorDefecto' => UnidadMedida::where('nombre_unidad_medida', 'PIEZAS')
                ->value('id_unidad_medida'),
        ];
    }

    private function prepararDatos(ProductoRequest $request, ?Producto $producto = null): array
    {
        $datos = $request->validated();

        $datos['controla_stock'] = $request->boolean('controla_stock');
        $datos['stock'] = $datos['controla_stock'] ? ($datos['stock'] ?? 0) : 0;
        $datos['costo_compra'] = $datos['costo_compra'] ?? 0;

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
