<?php

namespace App\Modules\Clientes\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteCorreo extends Model
{
    protected $table = 'cliente_correos';
    protected $primaryKey = 'id_cliente_correo';

    protected $fillable = ['id_cliente', 'correo'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }
}
