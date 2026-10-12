<?php

namespace App\Modules\Productos\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id_producto';

    protected $fillable = [
        'id_producto_sin',
        'id_categoria',
        'id_unidad_medida',
        'descripcion_producto',
        'detalle',
        'imagen',
        'costo_compra',
        'precio_venta',
        'controla_stock',
        'stock',
        'fecha_vencimiento',
        'producto_estado',
    ];

    protected function casts(): array
    {
        return [
            'controla_stock' => 'boolean',
            'fecha_vencimiento' => 'date',
        ];
    }

    protected function descripcionProducto(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => $valor === null ? null : mb_strtoupper(trim($valor), 'UTF-8'),
        );
    }

    public function productoSin()
    {
        return $this->belongsTo(ProductoSin::class, 'id_producto_sin', 'id_producto_sin');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidad_medida', 'id_unidad_medida');
    }
}
