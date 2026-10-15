<?php

namespace App\Modules\Auditoria\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auditoria\Models\Auditoria;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public const ACCIONES = ['CREAR', 'ACTUALIZAR', 'BAJA', 'REACTIVAR', 'LOGIN', 'LOGOUT'];

    public function index(Request $request)
    {
        $tabla = (new Auditoria)->getTable();

        $filtros = $request->only(['usuario', 'accion', 'tabla', 'desde', 'hasta']);

        $registros = Auditoria::query()
            ->leftJoin('usuarios', 'usuarios.id_usuario', '=', $tabla . '.id_usuario')
            ->select($tabla . '.*', 'usuarios.nombre as usuario_nombre')
            ->when($filtros['usuario'] ?? null, fn ($q, $v) => $q->where($tabla . '.id_usuario', $v))
            ->when($filtros['accion'] ?? null, fn ($q, $v) => $q->where($tabla . '.accion', $v))
            ->when($filtros['tabla'] ?? null, fn ($q, $v) => $q->where($tabla . '.tabla_afectada', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate($tabla . '.fecha_hora', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate($tabla . '.fecha_hora', '<=', $v))
            ->orderByDesc($tabla . '.fecha_hora')
            ->orderByDesc($tabla . '.id_auditoria')
            ->paginate(25)
            ->withQueryString();

        return view('auditoria.index', [
            'registros' => $registros,
            'filtros'   => $filtros,
            'usuarios'  => Usuario::orderBy('nombre')->get(['id_usuario', 'nombre']),
            'acciones'  => self::ACCIONES,
            'tablas'    => Auditoria::query()
                ->whereNotNull('tabla_afectada')
                ->distinct()
                ->orderBy('tabla_afectada')
                ->pluck('tabla_afectada'),
        ]);
    }
}
