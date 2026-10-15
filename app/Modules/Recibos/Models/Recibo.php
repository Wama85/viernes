<?php

namespace App\Modules\Recibos\Models;

use App\Modules\Auditoria\Auditable;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Cotizaciones\Models\CotizacionDetalle;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class Recibo extends Model
{
    use Auditable;

    protected $table = 'recibos';
    protected $primaryKey = 'id_recibo';

    public const PENDIENTE = 'PENDIENTE';
    public const APROBADO  = 'APROBADO';
    public const ANULADO   = 'ANULADO';

    public const FORMAS_PAGO = ['EFECTIVO', 'TRANSFERENCIA', 'QR', 'CHEQUE'];

    protected $fillable = [
        'anio', 'numero', 'id_cotizacion', 'id_usuario',
        'fecha_emision', 'monto', 'forma_pago', 'referencia_pago',
        'estado', 'id_aprobador', 'fecha_aprobacion',
    ];

    protected $casts = [
        'fecha_emision'    => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'monto'            => 'decimal:2',
    ];

    /** Ejemplo: REC-2026-0001 */
    public function getNumeroCompletoAttribute(): string
    {
        return sprintf('REC-%d-%04d', $this->anio, $this->numero);
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

    /** Ítems de la cotización que cubre este recibo. */
    public function detalles()
    {
        return $this->belongsToMany(
            CotizacionDetalle::class,
            'recibo_detalles',
            'id_recibo',
            'id_cotizacion_detalle',
            'id_recibo',
            'id_cotizacion_detalle'
        );
    }
}
