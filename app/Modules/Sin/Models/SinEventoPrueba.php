<?php

namespace App\Modules\Sin\Models;

use Illuminate\Database\Eloquent\Model;

class SinEventoPrueba extends Model
{
    protected $table = 'sin_eventos_prueba';
    protected $primaryKey = 'id_sin_evento_prueba';

    public const REGISTRADO = 'REGISTRADO';
    public const RECHAZADO  = 'RECHAZADO';

    protected $fillable = [
        'codigo_punto_venta', 'codigo_motivo', 'descripcion', 'cufd_evento',
        'fecha_inicio', 'fecha_fin', 'estado', 'codigo_recepcion', 'mensajes',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime:Y-m-d H:i:s.v',
        'fecha_fin'    => 'datetime:Y-m-d H:i:s.v',
    ];
}
