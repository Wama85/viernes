<?php

namespace App\Modules\Sin\Models;

use Illuminate\Database\Eloquent\Model;

class SinCatalogo extends Model
{
    protected $table = 'sin_catalogos';
    protected $primaryKey = 'id_sin_catalogo';

    protected $fillable = ['catalogo', 'datos', 'sincronizado_en'];

    protected $casts = [
        'datos'           => 'array',
        'sincronizado_en' => 'datetime',
    ];
}
