<?php

namespace App\Modules\Sin\Services;

use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Models\SinCufd;
use App\Modules\Sin\Models\SinCuis;
use App\Modules\Sin\Models\SinEventoPrueba;
use App\Modules\Sin\Models\SinFacturaPrueba;
use App\Modules\Sin\Models\SinPaquetePrueba;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SoapFault;

class SinPaqueteService
{
    private const TIPO_EMISION_FUERA_DE_LINEA = 2;
    private const ESTADO_VALIDADA  = 908;
    private const ESTADO_PENDIENTE = 901;

    private ?array $productos = null;
    private ?array $leyendas = null;
    private array $campos = [];

    public function __construct(private SinCliente $sin, private SinFacturaXml $xml)
    {
    }

    /** Arma un paquete de facturas fuera de línea dentro de la ventana del evento y lo envía. */
    public function enviar(SinEventoPrueba $evento, int $cantidad): SinPaquetePrueba
    {
        $cantidad = max(1, min(500, $cantidad));
        $pv = (int) config('sin.punto_venta');

        $cufdEvento = SinCufd::where('cufd', $evento->cufd_evento)->first();

        if (! $cufdEvento) {
            throw new RuntimeException('No encuentro el CUFD del evento #' . $evento->id_sin_evento_prueba);
        }

        if (! config('sin.razon_social')) {
            throw new RuntimeException('Falta SIN_RAZON_SOCIAL en el .env');
        }

        $primero = $this->siguienteNumero();
        $inicio = Carbon::parse($evento->getRawOriginal('fecha_inicio'));
        $paso = max(1, intdiv(19000, $cantidad));

        $archivos = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $numero = $primero + $i;
            $fecha = $inicio->copy()->addMilliseconds(100 + $i * $paso);
            $contenido = $this->facturaXml($cufdEvento, $numero, $fecha, $pv);

            if ($i === 0) {
                $errores = $this->xml->validar($contenido);

                if ($errores) {
                    throw new RuntimeException("La factura no cumple el XSD:\n - " . implode("\n - ", $errores));
                }
            }

            $archivos[sprintf('factura_%010d.xml', $numero)] = $contenido;
        }

        $comprimido = gzencode(SinTar::crear($archivos), 9);
        $hash = hash('sha256', $comprimido);

        $solicitud = $this->comunes() + [
            'archivo'          => $comprimido,
            'fechaEnvio'       => now()->format('Y-m-d\TH:i:s.v'),
            'hashArchivo'      => $hash,
            'cantidadFacturas' => $cantidad,
            'codigoEvento'     => (string) $evento->codigo_recepcion,
        ];

        [$datos, $transaccion] = $this->llamar('recepcionPaqueteFactura', $solicitud);

        $codigo = $datos->codigoRecepcion ?? null;
        $codigoEstado = isset($datos->codigoEstado) ? (int) $datos->codigoEstado : null;
        $ok = $transaccion && $codigo;

        return SinPaquetePrueba::create([
            'codigo_punto_venta'   => $pv,
            'id_sin_evento_prueba' => $evento->id_sin_evento_prueba,
            'codigo_motivo'        => $evento->codigo_motivo,
            'cantidad_facturas'    => $cantidad,
            'numero_inicio'        => $primero,
            'numero_fin'           => $primero + $cantidad - 1,
            'hash_archivo'         => $hash,
            'codigo_recepcion'     => $codigo,
            'codigo_estado'        => $codigoEstado,
            'estado'               => $ok ? SinPaquetePrueba::ENVIADO : SinPaquetePrueba::RECHAZADO,
            'mensajes'             => $this->texto($datos),
        ]);
    }

    /** Consulta al SIN el resultado de la validación de un paquete ya enviado. */
    public function validar(SinPaquetePrueba $paquete): SinPaquetePrueba
    {
        if (! $paquete->codigo_recepcion) {
            throw new RuntimeException('El paquete no tiene código de recepción.');
        }

        $solicitud = $this->comunes() + ['codigoRecepcion' => $paquete->codigo_recepcion];

        [$datos] = $this->llamar('validacionRecepcionPaqueteFactura', $solicitud);

        $codigoEstado = isset($datos->codigoEstado) ? (int) $datos->codigoEstado : null;
        $estado = $paquete->estado;

        if ($codigoEstado === self::ESTADO_VALIDADA) {
            $estado = SinPaquetePrueba::VALIDADO;
        } elseif ($codigoEstado !== self::ESTADO_PENDIENTE) {
            $estado = SinPaquetePrueba::RECHAZADO;
        }

        $paquete->update([
            'estado'                   => $estado,
            'codigo_estado_validacion' => $codigoEstado,
            'mensajes'                 => $this->texto($datos),
        ]);

        return $paquete->fresh();
    }

    // ---------------------------------------------------------------- internos

    private function comunes(): array
    {
        $cuis = SinCuis::actual();
        $cufd = SinCufd::actual();

        if (! $cuis || ! $cufd) {
            throw new RuntimeException('Falta CUIS o CUFD vigente para este punto de venta.');
        }

        return $this->sin->solicitudBase() + [
            'codigoDocumentoSector' => 1,
            'codigoEmision'         => self::TIPO_EMISION_FUERA_DE_LINEA,
            'tipoFacturaDocumento'  => 1,
            'cuis'                  => $cuis->cuis,
            'cufd'                  => $cufd->cufd,
        ];
    }

    /** @return array{0: object, 1: bool} */
    private function llamar(string $operacion, array $solicitud): array
    {
        $servicio = $this->sin->servicio('ServicioFacturacionCompraVenta');
        $campo = $this->campo($operacion);

        try {
            $respuesta = $servicio->{$operacion}([$campo => $solicitud]);
        } catch (SoapFault $e) {
            throw new RuntimeException('Error al comunicarse con el SIN: ' . $e->getMessage());
        }

        $arreglo = (array) $respuesta;
        $datos = reset($arreglo);

        return [$datos, (bool) ($datos->transaccion ?? false)];
    }

    /** Nombre del campo que envuelve la solicitud, leído del propio WSDL. */
    private function campo(string $operacion): string
    {
        if (isset($this->campos[$operacion])) {
            return $this->campos[$operacion];
        }

        $servicio = $this->sin->servicio('ServicioFacturacionCompraVenta');

        foreach ($servicio->__getTypes() as $tipo) {
            if (preg_match('/^struct ' . preg_quote($operacion, '/') . ' \{\s*\S+ (\w+);/', $tipo, $m)) {
                return $this->campos[$operacion] = $m[1];
            }
        }

        throw new RuntimeException("No encontré el campo de la solicitud de {$operacion} en el WSDL.");
    }

    private function siguienteNumero(): int
    {
        return max(
            (int) SinFacturaPrueba::max('numero'),
            (int) SinPaquetePrueba::max('numero_fin')
        ) + 1;
    }

    private function facturaXml(SinCufd $cufdEvento, int $numero, Carbon $fecha, int $pv): string
    {
        $detalles = $this->detalles();
        $total = round(array_sum(array_column($detalles, 'subTotal')), 2);

        $nit = config('sin.nit');
        $sucursal = (int) config('sin.sucursal');

        $cuf = SinCuf::calcular(
            $nit,
            $fecha,
            $sucursal,
            (int) config('sin.modalidad'),
            self::TIPO_EMISION_FUERA_DE_LINEA,
            1,
            1,
            $numero,
            $pv,
            (string) $cufdEvento->codigo_control
        );

        $cabecera = [
            'nitEmisor'                    => (string) $nit,
            'razonSocialEmisor'            => mb_substr(config('sin.razon_social'), 0, 200),
            'municipio'                    => mb_substr((string) config('sin.municipio'), 0, 25),
            'telefono'                     => config('sin.telefono'),
            'numeroFactura'                => $numero,
            'cuf'                          => $cuf,
            'cufd'                         => (string) $cufdEvento->cufd,
            'codigoSucursal'               => $sucursal,
            'direccion'                    => $cufdEvento->direccion ?: 'S/N',
            'codigoPuntoVenta'             => $pv,
            'fechaEmision'                 => $fecha->format('Y-m-d\TH:i:s.v'),
            'nombreRazonSocial'            => 'JUAN PEREZ',
            'codigoTipoDocumentoIdentidad' => 1,
            'numeroDocumento'              => '1234567',
            'complemento'                  => null,
            'codigoCliente'                => '1234567',
            'codigoMetodoPago'             => 1,
            'numeroTarjeta'                => null,
            'montoTotal'                   => $total,
            'montoTotalSujetoIva'          => $total,
            'codigoMoneda'                 => 1,
            'tipoCambio'                   => 1.0,
            'montoTotalMoneda'             => $total,
            'montoGiftCard'                => null,
            'descuentoAdicional'           => null,
            'codigoExcepcion'              => null,
            'cafc'                         => null,
            'leyenda'                      => $this->leyenda($detalles[0]['actividadEconomica']),
            'usuario'                      => 'PRUEBAS',
            'codigoDocumentoSector'        => 1,
        ];

        return $this->xml->construir($cabecera, $detalles);
    }

    private function detalles(): array
    {
        $productos = $this->productos();
        $detalles = [];

        for ($i = 0, $n = random_int(1, 2); $i < $n; $i++) {
            $p = $productos[array_rand($productos)];
            $cantidad = (float) random_int(1, 5);
            $precio = round(random_int(500, 20000) / 100, 2);

            $detalles[] = [
                'actividadEconomica' => (string) $p->codigo_actividad,
                'codigoProductoSin'  => (int) $p->codigo_producto_sin,
                'codigoProducto'     => 'PRUEBA-' . $p->codigo_producto_sin,
                'descripcion'        => 'PRODUCTO DE PRUEBA ' . ($i + 1),
                'cantidad'           => $cantidad,
                'unidadMedida'       => [57, 47][array_rand([57, 47])],
                'precioUnitario'     => $precio,
                'montoDescuento'     => 0.0,
                'subTotal'           => round($cantidad * $precio, 2),
                'numeroSerie'        => null,
                'numeroImei'         => null,
            ];
        }

        return $detalles;
    }

    private function productos(): array
    {
        if ($this->productos === null) {
            $consulta = DB::table('productos_sin');
            $habilitadas = $this->actividadesHabilitadas();

            if ($habilitadas) {
                $consulta->whereIn('codigo_actividad', $habilitadas);
            }

            $this->productos = $consulta->inRandomOrder()->limit(60)
                ->get(['codigo_actividad', 'codigo_producto_sin'])->all();

            if (! $this->productos) {
                throw new RuntimeException('No hay productos del SIN en la tabla productos_sin.');
            }
        }

        return $this->productos;
    }

    private function actividadesHabilitadas(): array
    {
        $codigos = [];

        foreach ($this->listaDe('Actividades') as $item) {
            if (is_array($item) && isset($item['codigoCaeb'])) {
                $codigos[] = (string) $item['codigoCaeb'];
            }
        }

        return $codigos;
    }

    private function leyenda(string $actividad): string
    {
        if ($this->leyendas === null) {
            $this->leyendas = [];

            foreach ($this->listaDe('ListaLeyendasFactura') as $item) {
                if (is_array($item) && ! empty($item['descripcionLeyenda'])) {
                    $this->leyendas[(string) ($item['codigoActividad'] ?? '')][] = $item['descripcionLeyenda'];
                }
            }
        }

        $candidatas = $this->leyendas[$actividad] ?? [];

        $texto = $candidatas
            ? $candidatas[array_rand($candidatas)]
            : 'Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los servicios que utilices.';

        return mb_substr($texto, 0, 200);
    }

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

    private function texto(object $datos): string
    {
        $lista = $datos->mensajesList ?? null;

        if (is_object($lista)) {
            $lista = [$lista];
        }

        $mensajes = [];

        foreach ((array) $lista as $m) {
            $m = (object) $m;
            $mensajes[] = trim(($m->codigo ?? '') . ': ' . ($m->descripcion ?? ''), ': ');
        }

        $texto = trim(($datos->codigoDescripcion ?? '') . ' | ' . implode(' | ', array_filter($mensajes)), ' |');

                $texto = $texto !== '' ? $texto : json_encode($datos, JSON_UNESCAPED_UNICODE);

        return mb_substr($texto, 0, 300);
    }
}
