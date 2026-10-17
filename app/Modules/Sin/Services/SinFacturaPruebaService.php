<?php

namespace App\Modules\Sin\Services;

use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Models\SinCufd;
use App\Modules\Sin\Models\SinFacturaPrueba;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SinFacturaPruebaService
{
    private const TIPO_EMISION_EN_LINEA = 1;
    private const TIPO_FACTURA_CREDITO_FISCAL = 1;
    private const DOCUMENTO_SECTOR_COMPRA_VENTA = 1;
    private const METODO_PAGO_EFECTIVO = 1;
    private const MONEDA_BOLIVIANO = 1;

    public function __construct(private SinFacturaXml $xml)
    {
    }

    public function generar(int $cantidadItems = 2, float $descuentoGlobal = 0.0, string $usuario = 'PRUEBAS'): SinFacturaPrueba
    {
        $cufd = SinCufd::actual();

        if (! $cufd) {
            throw new RuntimeException('No hay un CUFD vigente. Ejecuta: php artisan sin:cufd');
        }

        $razonSocial = config('sin.razon_social');

        if (! $razonSocial) {
            throw new RuntimeException('Falta SIN_RAZON_SOCIAL en el .env (y luego php artisan config:clear).');
        }

        $productos = $this->productosAlAzar(max(1, $cantidadItems));

        if ($productos->isEmpty()) {
            throw new RuntimeException('No hay productos del SIN en la tabla productos_sin.');
        }

        // Ítems
        $detalles = [];
        $suma = 0.0;

        foreach ($productos->values() as $i => $p) {
            $cantidad = (float) random_int(1, 5);
            $precio = round(random_int(500, 20000) / 100, 2);
            $descuento = $i === 0 ? round($cantidad * $precio * 0.05, 2) : 0.0;
            $subTotal = round($cantidad * $precio - $descuento, 2);
            $suma += $subTotal;

            $detalles[] = [
                'actividadEconomica' => (string) $p->codigo_actividad,
                'codigoProductoSin'  => (int) $p->codigo_producto_sin,
                'codigoProducto'     => 'PRUEBA-' . $p->codigo_producto_sin,
                'descripcion'        => 'PRODUCTO DE PRUEBA ' . ($i + 1),
                'cantidad'           => $cantidad,
                'unidadMedida'       => [57, 47][array_rand([57, 47])],
                'precioUnitario'     => $precio,
                'montoDescuento'     => $descuento,
                'subTotal'           => $subTotal,
                'numeroSerie'        => null,
                'numeroImei'         => null,
            ];
        }

        $suma = round($suma, 2);

        if ($descuentoGlobal >= $suma) {
            throw new RuntimeException('El descuento global debe ser menor al total.');
        }

        $montoTotal = round($suma - $descuentoGlobal, 2);

        // Identificación
        $numero = ((int) SinFacturaPrueba::max('numero')) + 1;
        $fecha = now();

        $nit = config('sin.nit');
        $sucursal = (int) config('sin.sucursal');
        $puntoVenta = (int) config('sin.punto_venta');

        $cuf = SinCuf::calcular(
            $nit,
            $fecha,
            $sucursal,
            (int) config('sin.modalidad'),
            self::TIPO_EMISION_EN_LINEA,
            self::TIPO_FACTURA_CREDITO_FISCAL,
            self::DOCUMENTO_SECTOR_COMPRA_VENTA,
            $numero,
            $puntoVenta,
            (string) $cufd->codigo_control
        );

        $cabecera = [
            'nitEmisor'                   => (string) $nit,
            'razonSocialEmisor'           => mb_substr($razonSocial, 0, 200),
            'municipio'                   => mb_substr((string) config('sin.municipio'), 0, 25),
            'telefono'                    => config('sin.telefono'),
            'numeroFactura'               => $numero,
            'cuf'                         => $cuf,
            'cufd'                        => (string) $cufd->cufd,
            'codigoSucursal'              => $sucursal,
            'direccion'                   => $cufd->direccion ?: 'S/N',
            'codigoPuntoVenta'            => $puntoVenta,
            'fechaEmision'                => $fecha->format('Y-m-d\TH:i:s.v'),
            'nombreRazonSocial'           => 'JUAN PEREZ',
            'codigoTipoDocumentoIdentidad' => 1,
            'numeroDocumento'             => '1234567',
            'complemento'                 => null,
            'codigoCliente'               => '1234567',
            'codigoMetodoPago'            => self::METODO_PAGO_EFECTIVO,
            'numeroTarjeta'               => null,
            'montoTotal'                  => $montoTotal,
            'montoTotalSujetoIva'         => $montoTotal,
            'codigoMoneda'                => self::MONEDA_BOLIVIANO,
            'tipoCambio'                  => 1.0,
            'montoTotalMoneda'            => $montoTotal,
            'montoGiftCard'               => null,
            'descuentoAdicional'          => $descuentoGlobal > 0 ? round($descuentoGlobal, 2) : null,
            'codigoExcepcion'             => null,
            'cafc'                        => null,
            'leyenda'                     => $this->leyenda($detalles[0]['actividadEconomica']),
            'usuario'                     => $usuario,
            'codigoDocumentoSector'       => self::DOCUMENTO_SECTOR_COMPRA_VENTA,
        ];

        $contenido = $this->xml->construir($cabecera, $detalles);
        $errores = $this->xml->validar($contenido);

        if ($errores) {
            throw new RuntimeException("El XML no cumple el XSD:\n - " . implode("\n - ", $errores));
        }

        return SinFacturaPrueba::create([
            'numero'        => $numero,
            'cuf'           => $cuf,
            'cufd'          => (string) $cufd->cufd,
            'fecha_emision' => $fecha,
            'monto_total'   => $montoTotal,
            'xml'           => $contenido,
            'estado'        => SinFacturaPrueba::GENERADA,
            'codigo_punto_venta' => $puntoVenta,
        ]);
    }

    /** Productos del SIN cuya actividad está habilitada para tu NIT. */
    private function productosAlAzar(int $cantidad)
    {
        $consulta = DB::table('productos_sin');

        $habilitadas = $this->actividadesHabilitadas();

        if ($habilitadas) {
            $consulta->whereIn('codigo_actividad', $habilitadas);
        }

        return $consulta->inRandomOrder()->limit($cantidad)->get(['codigo_actividad', 'codigo_producto_sin']);
    }

    private function actividadesHabilitadas(): array
    {
        $lista = $this->listaDe('Actividades');
        $codigos = [];

        foreach ($lista as $item) {
            if (is_array($item) && isset($item['codigoCaeb'])) {
                $codigos[] = (string) $item['codigoCaeb'];
            }
        }

        return $codigos;
    }

    private function leyenda(string $actividad): string
    {
        $candidatas = [];

        foreach ($this->listaDe('ListaLeyendasFactura') as $item) {
            if (is_array($item)
                && (string) ($item['codigoActividad'] ?? '') === $actividad
                && ! empty($item['descripcionLeyenda'])) {
                $candidatas[] = $item['descripcionLeyenda'];
            }
        }

        $texto = $candidatas
            ? $candidatas[array_rand($candidatas)]
            : 'Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los servicios que utilices.';

        return mb_substr($texto, 0, 200);
    }

    /** Primera lista que contenga el catálogo guardado. */
    private function listaDe(string $catalogo): array
    {
        $fila = SinCatalogo::where('catalogo', $catalogo)->first();

        foreach ((array) ($fila?->datos ?? []) as $valor) {
            if (is_array($valor)) {
                return isset($valor['codigoActividad']) || isset($valor['codigoCaeb']) ? [$valor] : $valor;
            }
        }

        return [];
    }
}
