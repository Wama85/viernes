<?php

namespace App\Modules\Empresa\Models;

use App\Modules\Auditoria\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use Auditable;

    protected $table = 'empresa';
    protected $primaryKey = 'id_empresa';

    protected $fillable = [
        'nombre_empresa', 'propietario', 'carnet', 'nit',
        'direccion', 'ciudad_pais', 'telefonos', 'correo', 'sitio_web',
        'cuentas_bancarias', 'formas_pago', 'logo', 'sello',
    ];

    protected function nombreEmpresa(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v === null ? null : mb_strtoupper(trim($v)));
    }

    protected function propietario(): Attribute
    {
        return Attribute::make(set: fn ($v) => $v === null ? null : mb_strtoupper(trim($v)));
    }

    /** Devuelve la fila de la empresa, o un modelo vacío si aún no se cargó. */
    public static function datos(): self
    {
        return static::first() ?? new static();
    }
}
