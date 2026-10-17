<?php

namespace App\Modules\Sin\Models;

use Illuminate\Database\Eloquent\Model;

class SinCufd extends Model
{
    protected $table = 'sin_cufd';
    protected $primaryKey = 'id_sin_cufd';

    protected $fillable = [
        'codigo_ambiente', 'codigo_sucursal', 'codigo_punto_venta',
        'cufd', 'codigo_control', 'direccion', 'fecha_vigencia', 'vigente',
    ];

    protected $casts = [
        'fecha_vigencia' => 'datetime',
        'vigente'        => 'boolean',
    ];

    /** El CUFD vigente de este ambiente, sucursal y punto de venta. */
    public static function actual(): ?self
    {
        return static::where('codigo_ambiente', config('sin.ambiente'))
            ->where('codigo_sucursal', config('sin.sucursal'))
            ->where('codigo_punto_venta', config('sin.punto_venta'))
            ->where('vigente', true)
            ->where('fecha_vigencia', '>', now())
            ->latest('id_sin_cufd')
            ->first();
    }
}
