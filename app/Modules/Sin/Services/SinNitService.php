<?php

namespace App\Modules\Sin\Services;

use App\Modules\Sin\Models\SinCuis;
use Illuminate\Support\Facades\Cache;
use SoapFault;

/** Consulta al SIN si un NIT existe (servicio verificarNit), para no emitir facturas que luego rechaza. */
class SinNitService
{
    public const ACTIVO = 'ACTIVO';
    public const INVALIDO = 'INVALIDO';
    public const ESPECIAL = 'ESPECIAL';
    public const DESCONOCIDO = 'DESCONOCIDO';

    /** El SIN responde 994 «NIT INEXISTENTE» cuando no lo reconoce (986 «NIT ACTIVO» cuando sí). */
    private const CODIGO_INEXISTENTE = 994;

    /** Nombre que el SIN exige para algunos códigos especiales (el 99001 admite cualquier nombre). */
    public const RAZON_SOCIAL_ESPECIAL = [
        '99002' => 'CONTROL TRIBUTARIO',
        '99003' => 'VENTAS MENORES DEL DIA',
    ];

    /** Códigos especiales (control tributario, ventas menores del día, etc.): no se verifican. */
    private const NIT_ESPECIALES = ['99001', '99002', '99003'];

    public function __construct(private SinCliente $sin, private SinComunicacion $comunicacion)
    {
    }

    /** Razón social obligatoria para un NIT especial, o null si el NIT no la impone. */
    public static function razonSocialEspecial(string $nit): ?string
    {
        return self::RAZON_SOCIAL_ESPECIAL[trim($nit)] ?? null;
    }

    /** @return array{estado: string, codigo: ?int, mensaje: string} */
    public function verificar(string $nit): array
    {
        $nit = trim($nit);

        if (in_array($nit, self::NIT_ESPECIALES, true)) {
            return $this->resultado(self::ESPECIAL, null, 'CÓDIGO ESPECIAL');
        }

        if (! preg_match('/^\d{1,15}$/', $nit)) {
            return $this->resultado(self::INVALIDO, null, 'NIT CON CARACTERES NO VÁLIDOS');
        }

        $clave = 'sin-nit-' . config('sin.ambiente') . '-' . $nit;

        if ($guardado = Cache::get($clave)) {
            return $guardado;
        }

        $resultado = $this->consultar($nit);

        // Solo se recuerdan las respuestas definitivas; un error de comunicación se reintenta la próxima vez.
        if ($resultado['estado'] === self::ACTIVO) {
            Cache::put($clave, $resultado, now()->addHours(6));
        } elseif ($resultado['estado'] === self::INVALIDO) {
            Cache::put($clave, $resultado, now()->addMinutes(30));
        }

        return $resultado;
    }

    /** @return array{estado: string, codigo: ?int, mensaje: string} */
    private function consultar(string $nit): array
    {
        $cuis = SinCuis::actual();

        if (! $cuis) {
            return $this->resultado(self::DESCONOCIDO, null, 'No hay un CUIS vigente para consultar el NIT.');
        }

        // Sin conexión con el SIN no se espera el tiempo límite de cada consulta.
        if (! $this->comunicacion->disponible()) {
            return $this->resultado(self::DESCONOCIDO, null, 'Sin conexión con el SIN.');
        }

        try {
            $respuesta = $this->sin->servicio('FacturacionCodigos')->verificarNit([
                'SolicitudVerificarNit' => [
                    'codigoAmbiente'      => (int) config('sin.ambiente'),
                    'codigoModalidad'     => (int) config('sin.modalidad'),
                    'codigoSistema'       => config('sin.codigo_sistema'),
                    'codigoSucursal'      => (int) config('sin.sucursal'),
                    'cuis'                => $cuis->cuis,
                    'nit'                 => (int) config('sin.nit'),
                    'nitParaVerificacion' => (int) $nit,
                ],
            ]);
        } catch (SoapFault $e) {
            return $this->resultado(self::DESCONOCIDO, null, 'No se pudo consultar el NIT al SIN: ' . $e->getMessage());
        }

        return $this->interpretar($respuesta);
    }

    /** @return array{estado: string, codigo: ?int, mensaje: string} */
    public function interpretar(object $respuesta): array
    {
        $datos = $respuesta->RespuestaVerificarNit ?? $respuesta;
        $mensaje = $datos->mensajesList ?? null;

        if (is_array($mensaje)) {
            $mensaje = $mensaje[0] ?? null;
        }

        $codigo = isset($mensaje->codigo) ? (int) $mensaje->codigo : null;
        $descripcion = trim((string) ($mensaje->descripcion ?? ''));

        if ((bool) ($datos->transaccion ?? false)) {
            return $this->resultado(self::ACTIVO, $codigo, $descripcion !== '' ? $descripcion : 'NIT ACTIVO');
        }

        if ($codigo === self::CODIGO_INEXISTENTE || preg_match('/\bNIT\b/i', $descripcion)) {
            return $this->resultado(self::INVALIDO, $codigo, $descripcion !== '' ? $descripcion : 'NIT INEXISTENTE');
        }

        // Otro error del SIN (CUIS vencido, etc.): no se sabe si el NIT es válido.
        return $this->resultado(self::DESCONOCIDO, $codigo, $descripcion !== '' ? $descripcion : 'Respuesta no reconocida del SIN.');
    }

    private function resultado(string $estado, ?int $codigo, string $mensaje): array
    {
        return ['estado' => $estado, 'codigo' => $codigo, 'mensaje' => $mensaje];
    }
}
