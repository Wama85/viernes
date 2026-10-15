<?php

namespace App\Modules\Facturas\Models;

use App\Modules\Auditoria\Auditable;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Cotizaciones\Models\CotizacionDetalle;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use Auditable;

    protected $table = 'facturas';
    protected $primaryKey = 'id_factura';

    // Estado interno
    public const PENDIENTE = 'PENDIENTE';
    public const APROBADA  = 'APROBADA';
    public const ANULADA   = 'ANULADA';

    // Estado frente al SIN
    public const SIN_NO_ENVIADA = 'NO_ENVIADA';
    public const SIN_VALIDADA   = 'VALIDADA';
    public const SIN_RECHAZADA  = 'RECHAZADA';

    public const FORMAS_PAGO = ['EFECTIVO', 'TRANSFERENCIA', 'QR', 'CHEQUE'];

    protected $fillable = [
        'numero', 'id_cotizacion', 'id_usuario', 'fecha_emision',
        'cliente_razon_social', 'cliente_id_tipo_documento', 'cliente_documento',
        'cliente_complemento', 'cliente_correo',
        'monto_total', 'forma_pago',
        'estado', 'id_aprobador', 'fecha_aprobacion',
        'estado_sin', 'cuf', 'codigo_recepcion', 'fecha_envio_sin', 'mensaje_sin',
    ];

    protected $casts = [
        'fecha_emision'    => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_envio_sin'  => 'datetime',
        'monto_total'      => 'decimal:2',
    ];

    /** Ejemplo: FAC-000123. Sin número hasta que se aprueba. */
    public function getNumeroCompletoAttribute(): string
    {
        return $this->numero ? sprintf('FAC-%06d', $this->numero) : 'SIN NÚMERO';
    }

    public function getClienteDocumentoCompletoAttribute(): string
    {
        return $this->cliente_documento
            . ($this->cliente_complemento ? '-' . $this->cliente_complemento : '');
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion', 'id_cotizacion');
    }

    public function emisor()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function aprobador()
    {
        return $this->belongsTo(Usuario::class, 'id_aprobador', 'id_usuario');
    }

    public function detalles()
    {
        return $this->belongsToMany(
            CotizacionDetalle::class,
            'factura_detalles',
            'id_factura',
            'id_cotizacion_detalle',
            'id_factura',
            'id_cotizacion_detalle'
        );
    }
}
