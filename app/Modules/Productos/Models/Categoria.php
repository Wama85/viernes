<?php

namespace App\Modules\Productos\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $primaryKey = 'id_categoria';

    protected $fillable = [
        'nombre_categoria',
        'categoria_estado',
    ];

    protected function nombreCategoria(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => $valor === null ? null : mb_strtoupper(trim($valor), 'UTF-8'),
        );
    }
}
