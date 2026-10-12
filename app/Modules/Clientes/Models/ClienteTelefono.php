<?php

namespace App\Modules\Clientes\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteTelefono extends Model
{
    protected $table = 'cliente_telefonos';
    protected $primaryKey = 'id_cliente_telefono';

    protected $fillable = ['id_cliente', 'telefono'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }
}
