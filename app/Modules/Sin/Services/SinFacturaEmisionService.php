<?php

namespace App\Modules\Sin\Services;

use App\Modules\Clientes\Models\TipoDocumento;
use App\Modules\Facturas\Models\Factura;
use App\Modules\Productos\Models\Producto;
use App\Modules\Productos\Models\UnidadMedida;
use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Models\SinCufd;
use App\Modules\Sin\Models\SinCuis;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use SoapFault;

/** Emite al SIN las facturas reales del sistema (el servicio de pruebas no se toca). */
class SinFacturaEmisionService
{
    private const TIPO_EMISION_EN_LINEA = 1;
    private const TIPO_EMISION_FUERA_DE_LINEA = 2;
    private const TIPO_FACTURA_CREDITO_FISCAL = 1;
    private const DOCUMENTO_SECTOR_COMPRA_VENTA = 1;
    private const MONEDA_BOLIVIANO = 1;
    private const TIPO_DOC_CI = 1;
    private const TIPO_DOC_NIT = 5;
    private const ESTADO_VALIDADA = 908;
    private const ESTADO_RECHAZADA = 902;
    private const ERROR_INTERNO_SIN = 2;

    /** Diferencia máxima, en Bs, entre el IVA enviado y el 13 % exacto del neto (probar con 0.01; si el SIN rechaza, poner 0). */
    private const TOLERANCIA_IVA = 0;

    /** Forma de pago del sistema => código de ParametricaTipoMetodoPago del SIN. */
    private const METODOS_PAGO = [
        'EFECTIVO'         => 1,
        'TARJETA'          => 2,
        'CHEQUE'           => 3, // solo facturas antiguas
        'TRANSFERENCIA'    => 7,
        'QR'               => 7,
        'EFECTIVO_TARJETA' => 10,
    ];

    public function __construct(
        private SinCliente $sin,
        private SinFacturaXml $xml,
        private SinNitService $nit,
        private SinComunicacion $comunicacion
    ) {
    }

    /**
     * Arma, valida y envía la factura al SIN. Lanza RuntimeException con un mensaje claro
     * si algo impide enviarla; si el SIN responde (aceptando o rechazando), devuelve la factura actualizada.
     */
    public function emitir(Factura $factura, bool $conExcepcion = false): Factura
    {
        // Evita envíos dobles (doble clic, dos pestañas).
        $candado = Cache::lock('sin-emitir-factura-' . $factura->getKey(), 120);

        if (! $candado->get()) {
            throw new RuntimeException('Esta factura ya se está enviando al SIN. Espera unos segundos.');
        }

        try {
            return $this->procesar($factura->fresh(['detalles', 'emisor']), $conExcepcion);
        } finally {
            $candado->release();
        }
    }

    private function procesar(Factura $factura, bool $conExcepcion = false): Factura
    {
        if ($factura->estado !== Factura::APROBADA) {
            throw new RuntimeException('Solo se envían al SIN las facturas aprobadas.');
        }

        if ($factura->estado_sin === Factura::SIN_FUERA_DE_LINEA) {
            throw new RuntimeException('Esta factura se emitió fuera de línea: se envía al SIN en un paquete cuando se recupera la conexión.');
        }

        if (! in_array($factura->estado_sin, [Factura::SIN_NO_ENVIADA, Factura::SIN_RECHAZADA], true)) {
            throw new RuntimeException('Esta factura ya fue validada por el SIN.');
        }

        if (! $factura->numero) {
            throw new RuntimeException('La factura no tiene número asignado.');
        }

        $cufd = SinCufd::actual();
        if (! $cufd) {
            throw new RuntimeException('No hay un CUFD vigente. Ejecuta: php artisan sin:cufd');
        }

        $cuis = SinCuis::actual();
        if (! $cuis) {
            throw new RuntimeException('No hay un CUIS vigente. Ejecuta: php artisan sin:cuis');
        }

        $razonSocial = config('sin.razon_social');
        if (! $razonSocial) {
            throw new RuntimeException('Falta SIN_RAZON_SOCIAL en el .env (y luego php artisan config:clear).');
        }

        $codigoMetodoPago = self::METODOS_PAGO[$factura->forma_pago]
            ?? throw new RuntimeException("La forma de pago «{$factura->forma_pago}» no tiene código del SIN.");

        // Con tarjeta el SIN recibe 16 dígitos: 4 primeros + 8 ceros + 4 últimos.
        $numeroTarjeta = $factura->llevaTarjeta() ? $factura->numero_tarjeta : null;

        if ($factura->llevaTarjeta() && ! preg_match('/^\d{16}$/', (string) $numeroTarjeta)) {
            throw new RuntimeException('La factura es con tarjeta pero no tiene el número de tarjeta (primeros y últimos 4 dígitos).');
        }

        $codigoDocumento = (int) TipoDocumento::whereKey($factura->cliente_id_tipo_documento)->value('codigo_sin');
        if (! $codigoDocumento) {
            throw new RuntimeException('El cliente no tiene un tipo de documento válido para el SIN.');
        }

        // ¿Hay comunicación con el SIN? Si no, la factura se emite fuera de línea (evento significativo).
        $fueraDeLinea = ! $this->comunicacion->disponible();

        // NIT del cliente: se consulta al SIN antes de enviar. Si no lo reconoce, solo se emite con excepción de NIT.
        $excepcion = false;

        if ($fueraDeLinea) {
            // Sin conexión no se puede verificar el NIT: las facturas con NIT llevan código de excepción.
            $excepcion = $codigoDocumento === self::TIPO_DOC_NIT;
        } elseif ($codigoDocumento === self::TIPO_DOC_NIT) {
            $pedida = $conExcepcion || (bool) $factura->codigo_excepcion;
            $verificacion = $this->nit->verificar((string) $factura->cliente_documento);

            if ($verificacion['estado'] === SinNitService::INVALIDO) {
                if (! $pedida) {
                    $factura->update(['mensaje_sin' => 'NIT_NO_VALIDO: ' . $verificacion['mensaje'] . '. El SIN no reconoce el NIT ' . $factura->cliente_documento . '.']);

                    throw new RuntimeException(
                        "El SIN no reconoce el NIT {$factura->cliente_documento} ({$verificacion['mensaje']}). "
                        . 'Si el cliente confirma que es su NIT, usa «Emitir con excepción de NIT» en la factura.'
                    );
                }

                $excepcion = true;
            } elseif ($verificacion['estado'] === SinNitService::DESCONOCIDO) {
                // No se pudo consultar: se respeta lo que pidió el usuario y el SIN decide.
                $excepcion = $pedida;
            }
        }

        // Algunos códigos especiales (99002, 99003) exigen un nombre fijo; el PDF y el XML deben llevar el mismo.
        $razonCliente = ($codigoDocumento === self::TIPO_DOC_NIT
            ? SinNitService::razonSocialEspecial((string) $factura->cliente_documento)
            : null) ?? (string) $factura->cliente_razon_social;

        $detalles = $this->detalles($factura);

        // Los precios del sistema ya incluyen el IVA (13%); la v3 pide precios netos y el IVA aparte.
        // Cada ítem se calcula como lo valida el SIN y el IVA es exactamente el 13 % del neto.
        // Los centavos de redondeo se absorben con el descuento adicional, para que el total
        // facturado sea el de la cotización.
        // El descuento adicional global (con IVA incluido) rebaja el total que debe quedar facturado.
        $totalConIva = round(array_sum(array_column($detalles, 'subTotal')) - (float) $factura->descuento_adicional, 2);
        $detalles = $this->aNeto($detalles, $totalConIva);
        $neto = round(array_sum(array_column($detalles, 'subTotal')), 2);

        [$descuentoAdicional, $iva, $montoTotal] = $this->ajusteFiscal($neto, $totalConIva);

        // La fecha de emisión es la del envío: el SIN la exige cercana a "ahora".
        $fecha = now();
        $nit = config('sin.nit');
        $sucursal = (int) config('sin.sucursal');
        $puntoVenta = (int) config('sin.punto_venta');
        $ambiente = (int) config('sin.ambiente');

        $cuf = SinCuf::calcular(
            $nit,
            $fecha,
            $sucursal,
            (int) config('sin.modalidad'),
            $fueraDeLinea ? self::TIPO_EMISION_FUERA_DE_LINEA : self::TIPO_EMISION_EN_LINEA,
            self::TIPO_FACTURA_CREDITO_FISCAL,
            self::DOCUMENTO_SECTOR_COMPRA_VENTA,
            (int) $factura->numero,
            $puntoVenta,
            (string) $cufd->codigo_control
        );

        $leyenda = $this->leyenda($detalles[0]['actividadEconomica']);

        $cabecera = [
            'nitEmisor'                    => (string) $nit,
            'razonSocialEmisor'            => mb_substr($razonSocial, 0, 200),
            'municipio'                    => mb_substr((string) config('sin.municipio'), 0, 25),
            'telefono'                     => config('sin.telefono'),
            'numeroFactura'                => (int) $factura->numero,
            'cuf'                          => $cuf,
            'cufd'                         => (string) $cufd->cufd,
            'codigoSucursal'               => $sucursal,
            'direccion'                    => $cufd->direccion ?: 'S/N',
            'codigoPuntoVenta'             => $puntoVenta,
            'fechaEmision'                 => $fecha->format('Y-m-d\TH:i:s.v'),
            'nombreRazonSocial'            => mb_substr($razonCliente, 0, 500),
            'codigoTipoDocumentoIdentidad' => $codigoDocumento,
            'numeroDocumento'              => (string) $factura->cliente_documento,
            'complemento'                  => ($codigoDocumento === self::TIPO_DOC_CI && $factura->cliente_complemento)
                ? $factura->cliente_complemento
                : null,
            'codigoCliente'                => (string) $factura->cliente_documento,
            'codigoMetodoPago'             => $codigoMetodoPago,
            'numeroTarjeta'                => $numeroTarjeta,
            'montoTotal'                   => $montoTotal,
            'montoTotalSujetoIva'          => $montoTotal,
            'codigoMoneda'                 => self::MONEDA_BOLIVIANO,
            'tipoCambio'                   => 1.0,
            'montoTotalMoneda'             => $montoTotal,
            'ivaFijo'                      => $iva,
            'montoGiftCard'                => null,
            'descuentoAdicional'           => $descuentoAdicional > 0 ? $descuentoAdicional : null,
            'codigoExcepcion'              => $excepcion ? 1 : null,
            'cafc'                         => null,
            'leyenda'                      => $leyenda,
            'usuario'                      => mb_substr((string) ($factura->emisor?->nombre ?? 'SISTEMA'), 0, 100),
            'codigoDocumentoSector'        => self::DOCUMENTO_SECTOR_COMPRA_VENTA,
        ];

        $contenido = $this->xml->construir($cabecera, $detalles);
        $errores = $this->xml->validar($contenido);

        if ($errores) {
            throw new RuntimeException("El XML no cumple el XSD:\n - " . implode("\n - ", $errores));
        }

        // Antes de enviar se guarda lo que se va a enviar (sin disparar la auditoría).
        $factura->updateQuietly([
            'fecha_emision'      => $fecha,
            'monto_total'        => $montoTotal,
            'cuf'                => $cuf,
            'cufd'               => (string) $cufd->cufd,
            'leyenda'            => $leyenda,
            'codigo_ambiente'    => $ambiente,
            'codigo_punto_venta' => $puntoVenta,
            'codigo_estado'      => null,
            'codigo_recepcion'   => null,
            'mensaje_sin'        => null,
            'codigo_excepcion'   => $excepcion,
            'codigo_emision'     => $fueraDeLinea ? Factura::EMISION_FUERA_DE_LINEA : Factura::EMISION_EN_LINEA,
            'cliente_razon_social' => $razonCliente,
            'xml'                => $contenido,
        ]);

        if ($fueraDeLinea) {
            // No se envía: queda guardada con su XML y espera el paquete que se manda al recuperar la conexión.
            $factura->update([
                'estado_sin'      => Factura::SIN_FUERA_DE_LINEA,
                'mensaje_sin'     => 'Emitida FUERA DE LÍNEA: no hay comunicación con el SIN. Se enviará en un paquete cuando se recupere la conexión.',
                'fecha_envio_sin' => null,
            ]);

            return $factura->fresh();
        }

        try {
            [$transaccion, $codigoEstado, $codigoRecepcion, $mensajes] = $this->enviar($contenido, (string) $cufd->cufd, (string) $cuis->cuis);
        } catch (SoapFault $e) {
            $factura->update(['mensaje_sin' => 'Error de comunicación con el SIN: ' . $e->getMessage()]);

            throw new RuntimeException('Error al comunicarse con el SIN: ' . $e->getMessage());
        }

        $estado = Factura::SIN_NO_ENVIADA;

        if ($transaccion && $codigoEstado === self::ESTADO_VALIDADA) {
            $estado = Factura::SIN_VALIDADA;
        } elseif ($codigoEstado === self::ERROR_INTERNO_SIN) {
            // Falla del propio SIN (no de la factura): sigue sin enviar y se puede reintentar.
            $estado = Factura::SIN_NO_ENVIADA;
        } elseif (! $transaccion || $codigoEstado === self::ESTADO_RECHAZADA) {
            $estado = Factura::SIN_RECHAZADA;
        }

        $factura->update([
            'estado_sin'       => $estado,
            'codigo_estado'    => $codigoEstado,
            'codigo_recepcion' => $codigoRecepcion,
            'mensaje_sin'      => $mensajes,
            'fecha_envio_sin'  => now(),
        ]);

        return $factura->fresh();
    }

    /** @return array{0: bool, 1: ?int, 2: ?string, 3: string} */
    private function enviar(string $xml, string $cufd, string $cuis): array
    {
        // Gzip del XML; PHP lo codifica a Base64 solo, porque el WSDL lo declara base64Binary.
        $comprimido = gzencode($xml, 9);

        $solicitud = $this->sin->solicitudBase() + [
            'codigoDocumentoSector' => self::DOCUMENTO_SECTOR_COMPRA_VENTA,
            'codigoEmision'         => self::TIPO_EMISION_EN_LINEA,
            'tipoFacturaDocumento'  => self::TIPO_FACTURA_CREDITO_FISCAL,
            'cuis'                  => $cuis,
            'cufd'                  => $cufd,
            'archivo'               => $comprimido,
            'fechaEnvio'            => now()->format('Y-m-d\TH:i:s.v'),
            'hashArchivo'           => hash('sha256', $comprimido),
        ];

        $respuesta = $this->sin
            ->servicio('ServicioFacturacionCompraVenta')
            ->recepcionFactura(['SolicitudServicioRecepcionFactura' => $solicitud]);

        $arreglo = (array) $respuesta;
        $datos = reset($arreglo);

        // Descripción general del SIN + mensajes de detalle, en un solo texto legible.
        $mensajes = $this->mensajes($datos->mensajesList ?? null);
        $descripcion = (string) ($datos->codigoDescripcion ?? '');
        $mensajes = trim($descripcion . ($mensajes !== '' ? ' | ' . $mensajes : ''), ' |');

        if ($mensajes === '') {
            $mensajes = json_encode($datos, JSON_UNESCAPED_UNICODE);
        }

        return [
            (bool) ($datos->transaccion ?? false),
            isset($datos->codigoEstado) ? (int) $datos->codigoEstado : null,
            $datos->codigoRecepcion ?? null,
            $mensajes,
        ];
    }

    /** Ítems de la factura en el formato del XML, con las comprobaciones previas. */
    private function detalles(Factura $factura): array
    {
        $items = $factura->detalles->sortBy('id_cotizacion_detalle')->values();

        if ($items->isEmpty()) {
            throw new RuntimeException('La factura no tiene ítems.');
        }

        $productos = Producto::with('productoSin')
            ->whereIn('id_producto', $items->pluck('id_producto'))
            ->get()
            ->keyBy('id_producto');

        $unidades = UnidadMedida::whereIn('id_unidad_medida', $items->pluck('id_unidad_medida'))
            ->pluck('codigo_sin', 'id_unidad_medida');

        $habilitadas = $this->actividadesHabilitadas();
        $filas = [];

        foreach ($items as $d) {
            $nombre = $d->descripcion;
            $sin = $productos->get($d->id_producto)?->productoSin;

            if (! $sin) {
                throw new RuntimeException("El producto «{$nombre}» no tiene un código del SIN asignado. Edítalo y elige su producto SIN.");
            }

            $actividad = (string) $sin->codigo_actividad;

            if ($habilitadas && ! in_array($actividad, $habilitadas, true)) {
                throw new RuntimeException("La actividad económica {$actividad} del producto «{$nombre}» no está habilitada para tu NIT.");
            }

            $unidad = (int) $unidades->get($d->id_unidad_medida, 0);

            if (! $unidad) {
                throw new RuntimeException("La unidad de medida del producto «{$nombre}» no tiene código del SIN.");
            }

            $descripcion = trim($d->descripcion . ($d->descripcion_adicional ? ' - ' . $d->descripcion_adicional : ''));

            $filas[] = [
                'actividadEconomica' => $actividad,
                'codigoProductoSin'  => (int) $sin->codigo_producto_sin,
                'codigoProducto'     => (string) $d->id_producto,
                'descripcion'        => mb_substr($descripcion, 0, 500),
                'cantidad'           => (float) $d->cantidad,
                'unidadMedida'       => $unidad,
                'precioUnitario'     => (float) $d->precio_unitario,
                'montoDescuento'     => (float) $d->descuento,
                'subTotal'           => (float) $d->subtotal,
                'numeroSerie'        => null,
                'numeroImei'         => null,
            ];
        }

        return $filas;
    }

    /**
     * Convierte los ítems (precios con IVA incluido) a netos, calculando cada uno como lo valida el SIN:
     * subtotal = cantidad × precio neto unitario − descuento. El IVA se obtiene luego como la
     * diferencia entre el total de la cotización y la suma de los netos.
     */
    private function aNeto(array $detalles, float $totalConIva): array
    {
        foreach ($detalles as $i => $d) {
            $precioNeto = round($d['precioUnitario'] / 1.13, 2);
            $descuentoNeto = round($d['montoDescuento'] / 1.13, 2);

            $detalles[$i]['precioUnitario'] = $precioNeto;
            $detalles[$i]['montoDescuento'] = $descuentoNeto;
            $detalles[$i]['subTotal'] = round($d['cantidad'] * $precioNeto - $descuentoNeto, 2);
        }

        return $detalles;
    }

    /**
     * Calcula [descuento adicional, IVA, total] para que el total facturado sea el de la cotización.
     * 1) Busca un descuento adicional (el global más 0 a 10 centavos de redondeo) con el que neto + 13 % exacto dé el total.
     * 2) Si no existe (p. ej. 100,00), usa un IVA que difiere hasta TOLERANCIA_IVA del 13 % exacto.
     * 3) Si tampoco, deja el total más cercano sin cobrar de más.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private function ajusteFiscal(float $neto, float $totalConIva): array
    {
        $mejor = null;

        // Descuento neto que, en teoría, deja justo el total pedido (incluye el descuento adicional global).
        // Se prueban 11 valores a su alrededor; sin descuento global es el rango 0 a 0,10 de siempre.
        $centro = max(0.0, round($neto - $totalConIva / 1.13, 2));
        $inicio = max(0.0, round($centro - 0.05, 2));

        for ($centavos = 0; $centavos <= 10; $centavos++) {
            $descuento = round($inicio + $centavos / 100, 2);
            $totalNeto = round($neto - $descuento, 2);
            $iva = round($totalNeto * 0.13, 2);
            $total = round($totalNeto + $iva, 2);

            if (abs($total - $totalConIva) < 0.005) {
                return [$descuento, $iva, $total];
            }
        }

        if (self::TOLERANCIA_IVA > 0) {
            for ($centavos = 0; $centavos <= 10; $centavos++) {
                $descuento = round($inicio + $centavos / 100, 2);
                $totalNeto = round($neto - $descuento, 2);
                $ivaExacto = round($totalNeto * 0.13, 2);
                $iva = round($totalConIva - $totalNeto, 2);

                if (abs($iva - $ivaExacto) <= self::TOLERANCIA_IVA + 0.00001) {
                    return [$descuento, $iva, $totalConIva];
                }
            }
        }

        for ($centavos = 0; $centavos <= 10; $centavos++) {
            $descuento = round($inicio + $centavos / 100, 2);
            $totalNeto = round($neto - $descuento, 2);
            $iva = round($totalNeto * 0.13, 2);
            $total = round($totalNeto + $iva, 2);
            $puntaje = abs($total - $totalConIva) + ($total > $totalConIva ? 0.0001 : 0);

            if ($mejor === null || $puntaje < $mejor[3] - 0.00001) {
                $mejor = [$descuento, $iva, $total, $puntaje];
            }
        }

        return [$mejor[0], $mejor[1], $mejor[2]];
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

    private function mensajes(mixed $lista): string
    {
        if (! $lista) {
            return '';
        }

        if (is_object($lista)) {
            $lista = [$lista];
        }

        $textos = [];

        foreach ((array) $lista as $m) {
            $m = (object) $m;
            $textos[] = trim(($m->codigo ?? '') . ': ' . ($m->descripcion ?? ''), ': ');
        }

        return implode(' | ', array_filter($textos));
    }
}
