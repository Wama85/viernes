<?php

namespace App\Modules\Clientes\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    protected $table = 'tipos_documento';
    protected $primaryKey = 'id_tipo_documento';

    protected $fillable = [
        'codigo_sin',
        'nombre_tipo_documento',
        'tipo_documento_estado',
    ];

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'id_tipo_documento', 'id_tipo_documento');
    }
}
