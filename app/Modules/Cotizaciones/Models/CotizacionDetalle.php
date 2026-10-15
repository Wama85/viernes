<?php

namespace App\Modules\Cotizaciones\Models;

use App\Modules\Productos\Models\Producto;
use App\Modules\Productos\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Model;

class CotizacionDetalle extends Model
{
    protected $table = 'cotizacion_detalles';
    protected $primaryKey = 'id_cotizacion_detalle';

    protected $fillable = [
        'id_cotizacion', 'id_producto', 'id_unidad_medida',
        'descripcion', 'descripcion_adicional',
        'cantidad', 'precio_unitario', 'descuento', 'subtotal',
    ];

    public function cotizacion()   { return $this->belongsTo(Cotizacion::class, 'id_cotizacion', 'id_cotizacion'); }
    public function producto()     { return $this->belongsTo(Producto::class, 'id_producto', 'id_producto'); }
    public function unidadMedida() { return $this->belongsTo(UnidadMedida::class, 'id_unidad_medida', 'id_unidad_medida'); }
public function recibos()
{
    return $this->belongsToMany(
        \App\Modules\Recibos\Models\Recibo::class,
        'recibo_detalles',
        'id_cotizacion_detalle',
        'id_recibo',
        'id_cotizacion_detalle',
        'id_recibo'
    );
}
public function facturas()
{
    return $this->belongsToMany(
        \App\Modules\Facturas\Models\Factura::class,
        'factura_detalles',
        'id_cotizacion_detalle',
        'id_factura',
        'id_cotizacion_detalle',
        'id_factura'
    );
}
    }
