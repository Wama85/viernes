<?php

namespace App\Modules\Sin\Models;

use Illuminate\Database\Eloquent\Model;

class SinPaquete extends Model
{
    public const ENVIADO = 'ENVIADO';
    public const VALIDADO = 'VALIDADO';
    public const RECHAZADO = 'RECHAZADO';

    protected $table = 'sin_paquetes';
    protected $primaryKey = 'id_sin_paquete';

    protected $fillable = [
        'id_sin_evento', 'cantidad_facturas', 'numero_inicio', 'numero_fin', 'hash_archivo',
        'codigo_recepcion', 'codigo_estado', 'codigo_estado_validacion', 'estado', 'mensajes',
    ];

    public function evento()
    {
        return $this->belongsTo(SinEvento::class, 'id_sin_evento', 'id_sin_evento');
    }
}
