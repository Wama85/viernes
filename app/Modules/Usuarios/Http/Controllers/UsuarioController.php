<?php

namespace App\Modules\Usuarios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Usuarios\Http\Requests\StoreUsuarioRequest;
use App\Modules\Usuarios\Http\Requests\UpdateUsuarioRequest;
use App\Modules\Usuarios\Models\Rol;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    private const ROL_ADMIN = 1;

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
        $datos = Arr::except($request->validated(), ['password_confirmation']);
        $datos['password'] = Hash::make($datos['password']);
        $datos['usuario_estado'] = 1;

        Usuario::create($datos);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(Usuario $usuario)
    {
        // Se incluye el rol actual aunque esté dado de baja, para no perderlo al editar.
        $roles = Rol::where('rol_estado', 1)
            ->orWhere('id_rol', $usuario->id_rol)
            ->get();

        return view('usuarios.edit', compact('usuario', 'roles'));
    }

    public function update(UpdateUsuarioRequest $request, Usuario $usuario)
    {
        $datos = Arr::except($request->validated(), ['password_confirmation']);

        if (! empty($datos['password'])) {
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
        // No se puede dar de baja al único administrador activo.
        if ((int) $usuario->usuario_estado === 1 && (int) $usuario->id_rol === self::ROL_ADMIN) {
            $activos = Usuario::where('id_rol', self::ROL_ADMIN)->where('usuario_estado', 1)->count();

            if ($activos <= 1) {
                return redirect()->route('usuarios.index')
                    ->with('error', 'No puedes dar de baja al único administrador activo.');
            }
        }

        $usuario->usuario_estado = $usuario->usuario_estado == 1 ? 0 : 1;
        $usuario->save();

        $mensaje = $usuario->usuario_estado == 1
            ? 'Usuario reactivado correctamente.'
            : 'Usuario dado de baja correctamente.';

        return redirect()->route('usuarios.index')->with('success', $mensaje);
    }
}
