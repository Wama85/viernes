<?php

namespace App\Modules\Productos\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'unidades_medida';
    protected $primaryKey = 'id_unidad_medida';

    protected $fillable = [
        'codigo_sin',
        'nombre_unidad_medida',
        'unidad_medida_estado',
    ];
}
