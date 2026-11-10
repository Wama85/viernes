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
    /** Emitida sin conexión (evento significativo): se envía al SIN en un paquete al recuperarla. */
    public const SIN_FUERA_DE_LINEA = 'FUERA_DE_LINEA';

    // Tipo de emisión del SIN
    public const EMISION_EN_LINEA = 1;
    public const EMISION_FUERA_DE_LINEA = 2;

    /** Formas de pago que se ofrecen al emitir una factura nueva. */
    public const FORMAS_PAGO = ['EFECTIVO', 'TARJETA', 'EFECTIVO_TARJETA', 'TRANSFERENCIA'];

    /** Formas de pago que llevan número de tarjeta (primeros 4 y últimos 4 dígitos). */
    public const FORMAS_CON_TARJETA = ['TARJETA', 'EFECTIVO_TARJETA'];

    /** Texto para pantalla y PDF (incluye CHEQUE por las facturas antiguas). */
    public const ETIQUETAS_PAGO = [
        'EFECTIVO'         => 'EFECTIVO',
        'TARJETA'          => 'TARJETA',
        'EFECTIVO_TARJETA' => 'EFECTIVO - TARJETA',
        'TRANSFERENCIA'    => 'TRANSFERENCIA BANCARIA',
        'QR'               => 'TRANSFERENCIA BANCARIA (QR)',
        'CHEQUE'           => 'CHEQUE',
    ];
        /** Texto del desplegable al emitir. El PDF usa ETIQUETAS_PAGO, igual al código que recibe el SIN. */
    public const ETIQUETAS_FORMULARIO = [
        'TRANSFERENCIA' => 'TRANSFERENCIA BANCARIA / QR',
    ];

    /** Ambiente piloto del SIN: las facturas emitidas ahí no tienen valor legal. */
    public const AMBIENTE_PILOTO = 2;

    protected $fillable = [
        'numero', 'id_cotizacion', 'id_usuario', 'fecha_emision',
        'cliente_razon_social', 'cliente_id_tipo_documento', 'cliente_documento',
        'cliente_complemento', 'cliente_correo',
        'monto_total', 'descuento_adicional', 'forma_pago', 'numero_tarjeta', 'codigo_excepcion', 'codigo_emision', 'id_sin_paquete', 'motivo_anulacion', 'reversiones',
        'estado', 'id_aprobador', 'fecha_aprobacion',
        'estado_sin', 'cuf', 'cufd', 'leyenda', 'codigo_ambiente', 'codigo_punto_venta',
        'codigo_estado', 'codigo_recepcion', 'fecha_envio_sin', 'mensaje_sin',
        'xml', 'fecha_correo', 'correo_enviado_a',
    ];

    /** El XML es pesado: no se incluye al convertir la factura a arreglo o JSON. */
    protected $hidden = ['xml'];

    protected $casts = [
        'fecha_emision'    => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_envio_sin'  => 'datetime',
        'fecha_correo'     => 'datetime',
        'monto_total'      => 'decimal:2',
        'descuento_adicional' => 'decimal:2',
        'codigo_excepcion' => 'boolean',
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

    public function getFormaPagoEtiquetaAttribute(): string
    {
        return self::ETIQUETAS_PAGO[$this->forma_pago] ?? (string) $this->forma_pago;
    }

    public function llevaTarjeta(): bool
    {
        return in_array($this->forma_pago, self::FORMAS_CON_TARJETA, true);
    }

    /** ¿Se emitió sin conexión y aún espera su paquete al SIN? */
    public function estaFueraDeLinea(): bool
    {
        return $this->estado_sin === self::SIN_FUERA_DE_LINEA;
    }

    /** Emitida en el ambiente de pruebas del SIN: no tiene valor legal. */
    public function esDePrueba(): bool
    {
        return (int) $this->codigo_ambiente === self::AMBIENTE_PILOTO;
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
