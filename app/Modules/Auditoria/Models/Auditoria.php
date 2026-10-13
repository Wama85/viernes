<?php

namespace App\Modules\Auditoria\Models;

use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Auditoria extends Model
{
    protected $table = 'auditoria';
    protected $primaryKey = 'id_auditoria';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario', 'accion', 'tabla_afectada', 'id_registro',
        'datos_anteriores', 'datos_nuevos', 'ip',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos'     => 'array',
        'fecha_hora'       => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    /** Punto único para registrar una acción en la bitácora. */
    public static function registrar(
        string $accion,
        ?Model $modelo = null,
        ?array $antes = null,
        ?array $despues = null,
        ?int $idUsuario = null
    ): void {
        // Seeders, tinker y migraciones no son acciones de un usuario.
        if (app()->runningInConsole()) {
            return;
        }

        static::create([
            'id_usuario'       => $idUsuario ?? Auth::id(),
            'accion'           => $accion,
            'tabla_afectada'   => $modelo?->getTable(),
            'id_registro'      => $modelo?->getKey(),
            'datos_anteriores' => $antes,
            'datos_nuevos'     => $despues,
            'ip'               => request()->ip(),
        ]);
    }
}
