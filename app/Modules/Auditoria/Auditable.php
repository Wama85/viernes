<?php

namespace App\Modules\Auditoria;

use App\Modules\Auditoria\Models\Auditoria;
use Illuminate\Support\Arr;

trait Auditable
{
    /** Campos que nunca se guardan en la bitácora. */
    private static array $camposOcultos = ['password', 'remember_token', 'updated_at', 'created_at'];

    public static function bootAuditable(): void
    {
        static::created(function ($modelo) {
            Auditoria::registrar(
                'CREAR',
                $modelo,
                null,
                Arr::except($modelo->getAttributes(), self::$camposOcultos)
            );
        });

        static::updated(function ($modelo) {
            $cambios = Arr::except($modelo->getChanges(), self::$camposOcultos);
            if (empty($cambios)) {
                return;
            }

            $antes = Arr::only($modelo->getOriginal(), array_keys($cambios));

            // Si solo cambió el campo *_estado, es una baja o una reactivación.
            $accion = 'ACTUALIZAR';
            $campoEstado = collect(array_keys($cambios))->first(fn ($c) => str_ends_with($c, '_estado'));
            if ($campoEstado && count($cambios) === 1) {
                $accion = $cambios[$campoEstado] ? 'REACTIVAR' : 'BAJA';
            }

            Auditoria::registrar($accion, $modelo, $antes, $cambios);
        });
    }
}
