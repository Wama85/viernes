<?php

namespace App\Modules\Cotizaciones\Models;

use App\Modules\Auditoria\Auditable;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Facturas\Models\Factura;
use App\Modules\Recibos\Models\Recibo;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    use Auditable;

    protected $table = 'cotizaciones';
    protected $primaryKey = 'id_cotizacion';

    public const PENDIENTE = 'PENDIENTE';
    public const APROBADA  = 'APROBADA';
    public const FACTURADA = 'FACTURADA';
    public const ANULADA   = 'ANULADA';

    protected $fillable = [
        'anio', 'numero', 'id_cliente', 'id_usuario',
        'fecha_emision', 'fecha_vencimiento', 'estado',
        'id_aprobador', 'fecha_aprobacion', 'observaciones', 'total',
    ];

    protected $casts = [
        'fecha_emision'     => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'fecha_aprobacion'  => 'datetime',
        'fecha_envio'       => 'datetime',
    ];

    /** Ejemplo: COT-2026-0001 */
    public function getNumeroCompletoAttribute(): string
    {
        return sprintf('COT-%d-%04d', $this->anio, $this->numero);
    }

    /** VENCIDA se calcula, no se guarda. */
    public function estaVencida(): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::APROBADA])
            && $this->fecha_vencimiento->isPast();
    }

    // ---------- Relaciones ----------

    public function cliente()   { return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente'); }
    public function creador()   { return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario'); }
    public function aprobador() { return $this->belongsTo(Usuario::class, 'id_aprobador', 'id_usuario'); }
    public function detalles()  { return $this->hasMany(CotizacionDetalle::class, 'id_cotizacion', 'id_cotizacion'); }
    public function recibos()   { return $this->hasMany(Recibo::class, 'id_cotizacion', 'id_cotizacion'); }
    public function facturas()  { return $this->hasMany(Factura::class, 'id_cotizacion', 'id_cotizacion'); }

    // ---------- Ítems libres (sin recibo ni factura activos) ----------

    public function detallesLibres()
    {
        return $this->detalles()
            ->whereDoesntHave('recibos', function ($q) {
                $q->where('recibos.estado', '!=', Recibo::ANULADO);
            })
            ->whereDoesntHave('facturas', function ($q) {
                $q->where('facturas.estado', '!=', Factura::ANULADA);
            });
    }

    public function detallesDisponibles()
    {
        return $this->detallesLibres()->get();
    }

    // ---------- Montos ----------

    /** Suma de recibos no anulados (pendientes y aprobados). */
    public function montoRecibido(): float
    {
        return (float) $this->recibos()->where('estado', '!=', Recibo::ANULADO)->sum('monto');
    }

    /** Suma de facturas no anuladas. */
    public function montoFacturado(): float
    {
        return (float) $this->facturas()->where('estado', '!=', Factura::ANULADA)->sum('monto_total');
    }

    /** Lo que aún no está en ningún recibo ni factura. */
    public function saldo(): float
    {
        return round((float) $this->total - $this->montoRecibido() - $this->montoFacturado(), 2);
    }

    public function tieneRecibos(): bool
    {
        return $this->recibos()->where('estado', '!=', Recibo::ANULADO)->exists();
    }

    public function tieneFacturas(): bool
    {
        return $this->facturas()->where('estado', '!=', Factura::ANULADA)->exists();
    }

    // ---------- Envío al cliente ----------

    public function fueEnviada(): bool
    {
        return $this->fecha_envio !== null;
    }

    public function puedeMarcarEnviada(): bool
    {
        return $this->estado === self::APROBADA
            && ! $this->estaVencida()
            && ! $this->fueEnviada();
    }

    /** Condición común para emitir recibo o factura. */
    private function puedeEmitirDocumentos(): bool
    {
        return $this->estado === self::APROBADA
            && ! $this->estaVencida()
            && $this->fueEnviada()
            && $this->detallesDisponibles()->isNotEmpty();
    }

    public function puedeEmitirRecibo(): bool
    {
        return $this->puedeEmitirDocumentos();
    }

    public function puedeEmitirFactura(): bool
    {
        return $this->puedeEmitirDocumentos();
    }

    // ---------- Edición ----------

    /**
     * Solo PENDIENTE o APROBADA. Si ya fue facturada, nadie la edita.
     * Si tiene recibos, solo el Administrador. El Vendedor solo edita las suyas.
     */
    public function puedeEditarPor($usuario): bool
    {
        if (! in_array($this->estado, [self::PENDIENTE, self::APROBADA])) {
            return false;
        }

        if ($this->tieneFacturas()) {
            return false;
        }

        if ($usuario->id_rol == 1) {
            return true;
        }

        return ! $this->tieneRecibos() && $this->id_usuario == $usuario->id_usuario;
    }
}
