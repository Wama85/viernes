<?php

namespace App\Modules\Sin\Models;

use Illuminate\Database\Eloquent\Model;

class SinFacturaPrueba extends Model
{
    protected $table = 'sin_facturas_prueba';
    protected $primaryKey = 'id_sin_factura_prueba';

    public const GENERADA  = 'GENERADA';
    public const VALIDADA  = 'VALIDADA';
    public const RECHAZADA = 'RECHAZADA';
    public const ANULADA   = 'ANULADA';

    protected $fillable = [
        'numero', 'codigo_punto_venta', 'cuf', 'cufd', 'fecha_emision', 'monto_total', 'xml',
        'estado', 'codigo_estado', 'codigo_recepcion', 'mensajes',
        'motivo_anulacion', 'reversiones', 'enviada_en',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime:Y-m-d H:i:s.v',
        'enviada_en'    => 'datetime',
        'monto_total'   => 'decimal:2',
    ];
}
