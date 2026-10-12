<?php

namespace App\Modules\Productos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Http\Requests\StoreCategoriaRequest;
use App\Modules\Productos\Http\Requests\UpdateCategoriaRequest;
use App\Modules\Productos\Models\Categoria;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::orderBy('nombre_categoria')->get();

        return view('categorias.index', compact('categorias'));
    }

    public function create()
    {
        return view('categorias.create');
    }

    public function store(StoreCategoriaRequest $request)
    {
        Categoria::create($request->validated() + ['categoria_estado' => 1]);

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría creada correctamente.');
    }

    public function edit(Categoria $categoria)
    {
        return view('categorias.edit', compact('categoria'));
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria)
    {
        $categoria->update($request->validated());

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    public function cambiarEstado(Categoria $categoria)
    {
        $categoria->categoria_estado = $categoria->categoria_estado == 1 ? 0 : 1;
        $categoria->save();

        $mensaje = $categoria->categoria_estado == 1
            ? 'Categoría reactivada correctamente.'
            : 'Categoría dada de baja correctamente.';

        return redirect()->route('categorias.index')->with('success', $mensaje);
    }
}
