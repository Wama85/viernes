<?php

namespace App\Modules\Usuarios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Usuarios\Http\Requests\StoreUsuarioRequest;
use App\Modules\Usuarios\Http\Requests\UpdateUsuarioRequest;
use App\Modules\Usuarios\Models\Rol;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = Usuario::with('rol')->orderBy('nombre')->get();

        return view('usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $roles = Rol::where('rol_estado', 1)->get();

        return view('usuarios.create', compact('roles'));
    }

    public function store(StoreUsuarioRequest $request)
    {
        $datos = $request->validated();
        $datos['password'] = Hash::make($datos['password']);
        $datos['usuario_estado'] = 1;

        Usuario::create($datos);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(Usuario $usuario)
    {
        $roles = Rol::where('rol_estado', 1)->get();

        return view('usuarios.edit', compact('usuario', 'roles'));
    }

    public function update(UpdateUsuarioRequest $request, Usuario $usuario)
    {
        $datos = $request->validated();

        if (!empty($datos['password'])) {
            $datos['password'] = Hash::make($datos['password']);
        } else {
            unset($datos['password']);
        }

        $usuario->update($datos);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function cambiarEstado(Usuario $usuario)
    {
        $usuario->usuario_estado = $usuario->usuario_estado == 1 ? 0 : 1;
        $usuario->save();

        $mensaje = $usuario->usuario_estado == 1
            ? 'Usuario reactivado correctamente.'
            : 'Usuario dado de baja correctamente.';

        return redirect()->route('usuarios.index')->with('success', $mensaje);
    }
}
