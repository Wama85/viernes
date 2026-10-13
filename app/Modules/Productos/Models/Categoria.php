<?php

namespace App\Modules\Productos\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Auditoria\Auditable;

class Categoria extends Model
{
    use Auditable;
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
