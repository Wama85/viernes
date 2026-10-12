<?php

namespace App\Modules\Clientes\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';
    protected $primaryKey = 'id_cliente';

    protected $fillable = [
        'id_tipo_documento',
        'documento_identidad',
        'complemento',
        'razon_social',
        'nombre_responsable',
        'direccion',
        'cliente_estado',
    ];

    protected function razonSocial(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => $this->aMayusculas($valor),
        );
    }

    protected function nombreResponsable(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => $this->aMayusculas($valor),
        );
    }

    protected function complemento(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => $this->aMayusculas($valor),
        );
    }

    private function aMayusculas(?string $valor): ?string
    {
        return $valor === null ? null : mb_strtoupper(trim($valor), 'UTF-8');
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'id_tipo_documento', 'id_tipo_documento');
    }

    public function correos()
    {
        return $this->hasMany(ClienteCorreo::class, 'id_cliente', 'id_cliente');
    }

    public function telefonos()
    {
        return $this->hasMany(ClienteTelefono::class, 'id_cliente', 'id_cliente');
    }
}
