<?php

namespace App\Modules\Sin\Models;
use Illuminate\Database\Eloquent\Model;


class SinEvento extends Model
{
    public const REGISTRADO = 'REGISTRADO';
    public const RECHAZADO = 'RECHAZADO';

    protected $table = 'sin_eventos';
    protected $primaryKey = 'id_sin_evento';

    protected $fillable = [
        'codigo_punto_venta', 'codigo_motivo', 'descripcion', 'cufd_evento', 'cufd',
        'fecha_inicio', 'fecha_fin', 'estado', 'codigo_recepcion', 'mensajes',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin'    => 'datetime',
    ];

    public function paquetes()
    {
        return $this->hasMany(SinPaquete::class, 'id_sin_evento', 'id_sin_evento');
    }
}
