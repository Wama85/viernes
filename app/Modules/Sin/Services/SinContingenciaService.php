<?php

namespace App\Modules\Sin\Services;

use App\Modules\Facturas\Models\Factura;
use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Models\SinCufd;
use App\Modules\Sin\Models\SinCuis;
use App\Modules\Sin\Models\SinEvento;
use App\Modules\Sin\Models\SinPaquete;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use SoapFault;

/**
 * Contingencia (eventos significativos): cuando vuelve la conexión, registra el evento en el SIN,
 * envía en un paquete las facturas emitidas fuera de línea y consulta su validación.
 */
class SinContingenciaService
{
    private const TIPO_EMISION_FUERA_DE_LINEA = 2;
    private const DOCUMENTO_SECTOR_COMPRA_VENTA = 1;
    private const TIPO_FACTURA_CREDITO_FISCAL = 1;
    private const ESTADO_VALIDADA = 908;
    private const ESTADO_PENDIENTE = 901;
    private const MAX_FACTURAS_POR_PAQUETE = 500;

    /** Plazo del SIN para enviar el paquete, contado desde la primera factura fuera de línea. */
    public const PLAZO_HORAS = 48;

    /** Motivos que permiten emitir fuera de línea con este sistema. */
    public const MOTIVOS = [
        1 => 'CORTE DEL SERVICIO DE INTERNET',
        2 => 'INACCESIBILIDAD AL SERVICIO WEB DE LA ADMINISTRACIÓN TRIBUTARIA',
        3 => 'INGRESO A ZONAS SIN INTERNET POR DESPLIEGUE DE PUNTOS DE VENTA',
        4 => 'VENTA EN LUGARES SIN INTERNET',
    ];

    private array $campos = [];

    public function __construct(private SinCliente $sin, private SinComunicacion $comunicacion)
    {
    }

    /** Facturas emitidas fuera de línea que aún no se enviaron al SIN. */
    public function pendientes(): Collection
    {
        return Factura::where('estado_sin', Factura::SIN_FUERA_DE_LINEA)
            ->where('estado', Factura::APROBADA)
            ->orderBy('numero')
            ->get();
    }

    /** Fecha y hora límite para enviar el paquete (null si no hay pendientes). */
    public function plazo(Collection $pendientes): ?Carbon
    {
        $primera = $pendientes->map(fn (Factura $f) => $this->fechaDelXml($f))->filter()->min();

        return $primera ? Carbon::parse($primera)->addHours(self::PLAZO_HORAS) : null;
    }

    /** Motivo => descripción, tomando el texto del catálogo del SIN cuando está sincronizado. */
    public function motivos(): array
    {
        $fila = SinCatalogo::where('catalogo', 'ParametricaEventosSignificativos')->first();
        $lista = [];

        foreach ((array) ($fila?->datos ?? []) as $valor) {
            if (is_array($valor)) {
                $lista = isset($valor['codigoClasificador']) ? [$valor] : $valor;
                break;
            }
        }

        $motivos = self::MOTIVOS;

        foreach ($lista as $item) {
            $codigo = (int) ($item['codigoClasificador'] ?? 0);

            if (isset($motivos[$codigo]) && ! empty($item['descripcion'])) {
                $motivos[$codigo] = (string) $item['descripcion'];
            }
        }

        return $motivos;
    }

    /**
     * Cierra la contingencia: registra el evento (uno por cada CUFD usado fuera de línea), envía el paquete
     * de facturas y consulta su validación.
     *
     * @return array<int, array{ok: bool, texto: string}> pasos realizados, para mostrarlos
     */
    public function cerrar(int $motivo): array
    {
        if (! isset(self::MOTIVOS[$motivo])) {
            throw new RuntimeException('Motivo del evento no válido (solo 1 a 4).');
        }

        $candado = Cache::lock('sin-cerrar-contingencia', 600);

        if (! $candado->get()) {
            throw new RuntimeException('Ya hay un cierre de contingencia en curso. Espera a que termine.');
        }

        try {
            return $this->procesarCierre($motivo);
        } finally {
            $candado->release();
        }
    }

    /** Consulta de nuevo la validación de un paquete que el SIN dejó pendiente. */
    public function validar(SinPaquete $paquete): SinPaquete
    {
        if (! $paquete->codigo_recepcion) {
            throw new RuntimeException('El paquete no tiene código de recepción.');
        }

        [$datos] = $this->llamar('ServicioFacturacionCompraVenta', 'validacionRecepcionPaqueteFactura', $this->comunes() + [
            'codigoRecepcion' => $paquete->codigo_recepcion,
        ]);

        $codigoEstado = isset($datos->codigoEstado) ? (int) $datos->codigoEstado : null;
        $texto = $this->texto($datos);
        $estado = $paquete->estado;

        if ($codigoEstado === self::ESTADO_VALIDADA) {
            $estado = SinPaquete::VALIDADO;
        } elseif ($codigoEstado !== self::ESTADO_PENDIENTE) {
            $estado = SinPaquete::RECHAZADO;
        }

        $paquete->update([
            'estado'                   => $estado,
            'codigo_estado_validacion' => $codigoEstado,
            'mensajes'                 => $texto,
        ]);

        if ($estado !== SinPaquete::ENVIADO) {
            $this->actualizarFacturas($paquete, $estado === SinPaquete::VALIDADO, $codigoEstado, $texto);
        }

        return $paquete->fresh();
    }

    // ------------------------------------------------------------------

    private function procesarCierre(int $motivo): array
    {
        $pasos = [];

        $pendientes = $this->pendientes();

        if ($pendientes->isEmpty()) {
            throw new RuntimeException('No hay facturas emitidas fuera de línea pendientes de enviar.');
        }

        if (! $this->comunicacion->disponible()) {
            throw new RuntimeException('Todavía no hay comunicación con el SIN. Cuando se recupere, vuelve a intentarlo.');
        }

        $cuis = SinCuis::actual();

        if (! $cuis) {
            throw new RuntimeException('No hay un CUIS vigente. Ejecuta: php artisan sin:cuis');
        }

        // Al recuperar la conexión se pide un CUFD nuevo: es el vigente al registrar el evento y enviar el paquete.
        $nuevo = $this->nuevoCufd($cuis);
        $pasos[] = ['ok' => true, 'texto' => 'CUFD nuevo obtenido (vigente hasta ' . $nuevo->fecha_vigencia->format('d/m/Y H:i') . ').'];

        // Un evento por cada CUFD con el que se emitió fuera de línea.
        foreach ($pendientes->groupBy('cufd') as $cufdEvento => $facturas) {
            $evento = $this->registrarEvento($motivo, (string) $cufdEvento, $nuevo, $cuis, $facturas);

            if ($evento->estado !== SinEvento::REGISTRADO) {
                $pasos[] = ['ok' => false, 'texto' => 'El SIN no registró el evento: ' . $evento->mensajes];

                return $pasos;
            }

            $pasos[] = [
                'ok'    => true,
                'texto' => 'Evento registrado (código ' . $evento->codigo_recepcion . ') del '
                    . $evento->fecha_inicio->format('d/m/Y H:i:s') . ' al ' . $evento->fecha_fin->format('d/m/Y H:i:s') . '.',
            ];

            foreach ($facturas->chunk(self::MAX_FACTURAS_POR_PAQUETE) as $grupo) {
                $paquete = $this->enviarPaquete($evento, $grupo->values(), $nuevo, $cuis);
                $pasos[] = [
                    'ok'    => $paquete->estado !== SinPaquete::RECHAZADO,
                    'texto' => 'Paquete de ' . $paquete->cantidad_facturas . ' factura(s) (FAC-'
                        . sprintf('%06d', $paquete->numero_inicio) . ' a FAC-' . sprintf('%06d', $paquete->numero_fin) . '): '
                        . $paquete->estado . ($paquete->mensajes ? ' · ' . $paquete->mensajes : ''),
                ];
            }
        }

        // La caída ya quedó registrada como evento.
        $this->comunicacion->limpiarCaida();

        return $pasos;
    }

    private function nuevoCufd(SinCuis $cuis): SinCufd
    {
        $solicitud = $this->sin->solicitudBase();
        $solicitud['cuis'] = $cuis->cuis;

        [$datos] = $this->llamar('FacturacionCodigos', 'cufd', $solicitud, 'SolicitudCufd');

        if (! ($datos->transaccion ?? false) || empty($datos->codigo)) {
            throw new RuntimeException('El SIN no entregó el CUFD: ' . $this->texto($datos));
        }

        $vigencia = Carbon::parse($datos->fechaVigencia)->setTimezone(config('app.timezone'));

        return SinCufd::updateOrCreate(
            ['cufd' => $datos->codigo],
            [
                'codigo_ambiente'    => config('sin.ambiente'),
                'codigo_sucursal'    => config('sin.sucursal'),
                'codigo_punto_venta' => config('sin.punto_venta'),
                'codigo_control'     => $datos->codigoControl,
                'direccion'          => $datos->direccion ?? null,
                'fecha_vigencia'     => $vigencia,
                'vigente'            => true,
            ]
        );
    }

    private function registrarEvento(int $motivo, string $cufdEvento, SinCufd $nuevo, SinCuis $cuis, $facturas): SinEvento
    {
        $fechas = $facturas->map(fn (Factura $f) => $this->fechaDelXml($f))->filter()->values();

        if ($fechas->isEmpty()) {
            throw new RuntimeException('No se pudo leer la fecha de emisión de las facturas fuera de línea.');
        }

        $primera = Carbon::parse($fechas->min());
        $ultima = Carbon::parse($fechas->max());

        // El CUFD del evento: el CUFD dura 24 h; su emisión es su vigencia menos un día.
        $cufdFila = SinCufd::where('cufd', $cufdEvento)->first();
        $emisionCufd = $cufdFila ? Carbon::parse($cufdFila->fecha_vigencia)->subDay() : null;
        $vigenciaCufd = $cufdFila ? Carbon::parse($cufdFila->fecha_vigencia) : null;

        // Inicio: poco antes de la primera factura fuera de línea, sin salirse del CUFD.
        $inicio = $primera->copy()->subSeconds(5);

        if ($emisionCufd && $inicio->lessThan($emisionCufd)) {
            $inicio = $emisionCufd->copy()->addSeconds(1);
        }

        // No se solapa con un evento ya registrado con el mismo CUFD.
        $ultimoFin = SinEvento::where('cufd_evento', $cufdEvento)->where('estado', SinEvento::REGISTRADO)->max('fecha_fin');

        if ($ultimoFin && $inicio->lessThanOrEqualTo(Carbon::parse($ultimoFin))) {
            $inicio = Carbon::parse($ultimoFin)->addSeconds(1);
        }

        // Fin: poco antes de ahora (el SIN no admite fechas futuras) y sin pasar del CUFD.
        $fin = now()->subSeconds(30);

        if ($vigenciaCufd && $fin->greaterThan($vigenciaCufd)) {
            $fin = $vigenciaCufd->copy();
        }

        if ($fin->lessThanOrEqualTo($ultima)) {
            throw new RuntimeException('La última factura es muy reciente. Espera un minuto y vuelve a intentarlo.');
        }

        if ($inicio->greaterThanOrEqualTo($primera)) {
            $inicio = $primera->copy()->subSeconds(1);
        }

        $descripcion = $this->motivos()[$motivo];
        $pv = (int) config('sin.punto_venta');

        $solicitud = [
            'codigoAmbiente'        => (int) config('sin.ambiente'),
            'codigoMotivoEvento'    => $motivo,
            'codigoPuntoVenta'      => $pv,
            'codigoSistema'         => config('sin.codigo_sistema'),
            'codigoSucursal'        => (int) config('sin.sucursal'),
            'cufd'                  => $nuevo->cufd,
            'cufdEvento'            => $cufdEvento,
            'cuis'                  => $cuis->cuis,
            'descripcion'           => mb_substr($descripcion, 0, 200),
            'fechaHoraFinEvento'    => $fin->format('Y-m-d\TH:i:s.v'),
            'fechaHoraInicioEvento' => $inicio->format('Y-m-d\TH:i:s.v'),
            'nit'                   => (int) config('sin.nit'),
        ];

        [$datos] = $this->llamar('FacturacionOperaciones', 'registroEventoSignificativo', $solicitud, 'SolicitudEventoSignificativo');

        $codigo = $datos->codigoRecepcionEventoSignificativo ?? ($datos->codigoRecepcion ?? null);
        $ok = ($datos->transaccion ?? false) && $codigo;

        return SinEvento::create([
            'codigo_punto_venta' => $pv,
            'codigo_motivo'      => $motivo,
            'descripcion'        => mb_substr($descripcion, 0, 200),
            'cufd_evento'        => $cufdEvento,
            'cufd'               => $nuevo->cufd,
            'fecha_inicio'       => $inicio,
            'fecha_fin'          => $fin,
            'estado'             => $ok ? SinEvento::REGISTRADO : SinEvento::RECHAZADO,
            'codigo_recepcion'   => $codigo ? (string) $codigo : null,
            'mensajes'           => $this->texto($datos),
        ]);
    }

    private function enviarPaquete(SinEvento $evento, $facturas, SinCufd $nuevo, SinCuis $cuis): SinPaquete
    {
        $archivos = [];

        foreach ($facturas as $factura) {
            if (blank($factura->xml)) {
                throw new RuntimeException("La factura {$factura->numero_completo} no tiene su XML guardado.");
            }

            $archivos[sprintf('factura_%010d.xml', $factura->numero)] = (string) $factura->xml;
        }

        $comprimido = gzencode(SinTar::crear($archivos), 9);
        $hash = hash('sha256', $comprimido);

        $solicitud = $this->comunes($nuevo, $cuis) + [
            'archivo'          => $comprimido,
            'fechaEnvio'       => now()->format('Y-m-d\TH:i:s.v'),
            'hashArchivo'      => $hash,
            'cantidadFacturas' => $facturas->count(),
            'codigoEvento'     => (string) $evento->codigo_recepcion,
        ];

        [$datos, $transaccion] = $this->llamar('ServicioFacturacionCompraVenta', 'recepcionPaqueteFactura', $solicitud);

        $codigo = $datos->codigoRecepcion ?? null;
        $codigoEstado = isset($datos->codigoEstado) ? (int) $datos->codigoEstado : null;
        $ok = $transaccion && $codigo;

        $paquete = SinPaquete::create([
            'id_sin_evento'     => $evento->id_sin_evento,
            'cantidad_facturas' => $facturas->count(),
            'numero_inicio'     => $facturas->min('numero'),
            'numero_fin'        => $facturas->max('numero'),
            'hash_archivo'      => $hash,
            'codigo_recepcion'  => $codigo ? (string) $codigo : null,
            'codigo_estado'     => $codigoEstado,
            'estado'            => $ok ? SinPaquete::ENVIADO : SinPaquete::RECHAZADO,
            'mensajes'          => $this->texto($datos),
        ]);

        if (! $ok) {
            return $paquete;
        }

        // Las facturas quedan asociadas al paquete y se consulta su validación.
        Factura::whereIn('id_factura', $facturas->pluck('id_factura'))->update(['id_sin_paquete' => $paquete->id_sin_paquete]);

        try {
            return $this->validar($paquete);
        } catch (RuntimeException) {
            // Si la consulta falla, el paquete queda ENVIADO y se consulta desde la pantalla.
            return $paquete;
        }
    }

    private function actualizarFacturas(SinPaquete $paquete, bool $validadas, ?int $codigoEstado, string $texto): void
    {
        Factura::where('id_sin_paquete', $paquete->id_sin_paquete)
            ->where('estado_sin', Factura::SIN_FUERA_DE_LINEA)
            ->get()
            ->each(function (Factura $factura) use ($paquete, $validadas, $codigoEstado, $texto) {
                $factura->update([
                    'estado_sin'       => $validadas ? Factura::SIN_VALIDADA : Factura::SIN_RECHAZADA,
                    'codigo_estado'    => $codigoEstado,
                    'codigo_recepcion' => $paquete->codigo_recepcion,
                    'mensaje_sin'      => $validadas
                        ? 'Validada por el SIN en el paquete ' . $paquete->codigo_recepcion . ' (emitida fuera de línea).'
                        : 'El SIN rechazó el paquete: ' . $texto,
                    'fecha_envio_sin'  => now(),
                ]);
            });
    }

    /** Fecha de emisión que lleva el XML guardado (la que valida el SIN). */
    private function fechaDelXml(Factura $factura): ?string
    {
        if (preg_match('/<fechaEmision>([^<]+)<\/fechaEmision>/', (string) $factura->xml, $m)) {
            return $m[1];
        }

        return null;
    }

    private function comunes(?SinCufd $cufd = null, ?SinCuis $cuis = null): array
    {
        $cuis ??= SinCuis::actual();
        $cufd ??= SinCufd::actual();

        if (! $cuis || ! $cufd) {
            throw new RuntimeException('Falta CUIS o CUFD vigente para este punto de venta.');
        }

        return $this->sin->solicitudBase() + [
            'codigoDocumentoSector' => self::DOCUMENTO_SECTOR_COMPRA_VENTA,
            'codigoEmision'         => self::TIPO_EMISION_FUERA_DE_LINEA,
            'tipoFacturaDocumento'  => self::TIPO_FACTURA_CREDITO_FISCAL,
            'cuis'                  => $cuis->cuis,
            'cufd'                  => $cufd->cufd,
        ];
    }

    /** @return array{0: object, 1: bool} */
    private function llamar(string $servicio, string $operacion, array $solicitud, ?string $campo = null): array
    {
        $cliente = $this->sin->servicio($servicio);

        try {
            $respuesta = $cliente->{$operacion}([($campo ?? $this->campo($servicio, $operacion)) => $solicitud]);
        } catch (SoapFault $e) {
            throw new RuntimeException('Error al comunicarse con el SIN: ' . $e->getMessage());
        }

        $arreglo = (array) $respuesta;
        $datos = reset($arreglo);

        return [$datos, (bool) ($datos->transaccion ?? false)];
    }

    /** Nombre del campo que envuelve la solicitud, leído del propio WSDL. */
    private function campo(string $servicio, string $operacion): string
    {
        if (isset($this->campos[$operacion])) {
            return $this->campos[$operacion];
        }

        foreach ($this->sin->servicio($servicio)->__getTypes() as $tipo) {
            if (preg_match('/^struct ' . preg_quote($operacion, '/') . ' \{\s*\S+ (\w+);/', $tipo, $m)) {
                return $this->campos[$operacion] = $m[1];
            }
        }

        throw new RuntimeException("No encontré el campo de la solicitud de {$operacion} en el WSDL.");
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

        return mb_substr($texto, 0, 600);
    }
}
