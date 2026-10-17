<?php

namespace App\Modules\Sin\Models;

use Illuminate\Database\Eloquent\Model;

class SinPaquetePrueba extends Model
{
    protected $table = 'sin_paquetes_prueba';
    protected $primaryKey = 'id_sin_paquete_prueba';

    public const ENVIADO   = 'ENVIADO';
    public const VALIDADO  = 'VALIDADO';
    public const RECHAZADO = 'RECHAZADO';

    protected $fillable = [
        'codigo_punto_venta', 'id_sin_evento_prueba', 'codigo_motivo', 'cantidad_facturas',
        'numero_inicio', 'numero_fin', 'hash_archivo', 'codigo_recepcion', 'codigo_estado',
        'estado', 'codigo_estado_validacion', 'mensajes',
    ];
}
