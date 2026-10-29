<?php

namespace App\Modules\Productos\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoSin extends Model
{
    protected $table = 'productos_sin';
    protected $primaryKey = 'id_producto_sin';

    protected $fillable = [
        'codigo_producto_sin',
        'codigo_actividad',
        'descripcion_producto_sin',
        'producto_sin_estado',
        'favorito',
    ];
}
