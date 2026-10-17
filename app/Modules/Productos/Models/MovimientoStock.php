<?php

namespace App\Modules\Productos\Models;

use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    protected $table = 'movimientos_stock';
    protected $primaryKey = 'id_movimiento_stock';
    public $timestamps = false;

    public const ENTRADA    = 'ENTRADA';
    public const SALIDA     = 'SALIDA';
    public const DEVOLUCION = 'DEVOLUCION';
    public const AJUSTE     = 'AJUSTE';

    protected $fillable = [
        'id_producto', 'tipo', 'cantidad', 'stock_anterior', 'stock_posterior',
        'tipo_documento', 'id_documento', 'motivo', 'revertido',
        'id_usuario', 'fecha_hora',
    ];

    protected $casts = [
        'cantidad'        => 'float',
        'stock_anterior'  => 'float',
        'stock_posterior' => 'float',
        'revertido'       => 'boolean',
        'fecha_hora'      => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
