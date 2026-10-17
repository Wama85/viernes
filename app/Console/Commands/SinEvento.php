<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Models\SinCufd;
use App\Modules\Sin\Models\SinCuis;
use App\Modules\Sin\Models\SinEventoPrueba;
use App\Modules\Sin\Services\SinCliente;
use App\Support\ConPuntoVenta;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class SinEvento extends Command
{
    use ConPuntoVenta;

    protected $signature = 'sin:evento
                            {--motivo=0 : Motivo 1 a 7 (0 = recorre los 7 motivos)}
                            {--cantidad=1 : Cuántos eventos por motivo}
                            {--pv= : Punto de venta (por defecto el del .env)}';

    protected $description = 'Registra eventos significativos en el SIN (Etapa V)';

    private const DURACION_SEGUNDOS = 20;
    private const SEPARACION_SEGUNDOS = 30;

    public function handle(SinCliente $sin): int
    {
        $pv = $this->puntoVenta();
        $this->line("Punto de venta: {$pv}");

        $cuis = SinCuis::actual();
        $cufd = SinCufd::actual();

        if (! $cuis || ! $cufd) {
            $this->error("Falta CUIS o CUFD vigente para el punto de venta {$pv}.");

            return self::FAILURE;
        }

        $descripciones = $this->descripciones();
        $motivos = (int) $this->option('motivo') > 0 ? [(int) $this->option('motivo')] : range(1, 7);
        $cantidad = max(1, (int) $this->option('cantidad'));

        // El CUFD dura 24 h: su emisión es la fecha de vigencia menos un día
        $emision = Carbon::parse($cufd->fecha_vigencia)->subDay();
        $limite = now()->subSeconds(30);

        $servicio = $sin->servicio('FacturacionOperaciones');
        $correctos = 0;
        $fallidos = 0;

        foreach ($motivos as $motivo) {
            for ($i = 1; $i <= $cantidad; $i++) {
                $inicio = $this->siguienteInicio($pv, $cufd->cufd, $emision);
                $fin = $inicio->copy()->addSeconds(self::DURACION_SEGUNDOS);

                if ($fin->greaterThan($limite)) {
                    $espera = (int) ceil($inicio->diffInSeconds(now(), false) * -1 + self::DURACION_SEGUNDOS + 30);
                    $this->warn('La ventana del evento llegaría al futuro. Espera unos '
                        . max(1, (int) ceil($espera / 60)) . ' minuto(s) y repite el comando.');
                    $this->line("Registrados: {$correctos} | Con problemas: {$fallidos}");

                    return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
                }

                $descripcion = $descripciones[$motivo] ?? "MOTIVO {$motivo}";

                $solicitud = [
                    'codigoAmbiente'        => config('sin.ambiente'),
                    'codigoMotivoEvento'    => $motivo,
                    'codigoPuntoVenta'      => $pv,
                    'codigoSistema'         => config('sin.codigo_sistema'),
                    'codigoSucursal'        => config('sin.sucursal'),
                    'cufd'                  => $cufd->cufd,
                    'cufdEvento'            => $cufd->cufd,
                    'cuis'                  => $cuis->cuis,
                    'descripcion'           => $descripcion,
                    'fechaHoraFinEvento'    => $fin->format('Y-m-d\TH:i:s.v'),
                    'fechaHoraInicioEvento' => $inicio->format('Y-m-d\TH:i:s.v'),
                    'nit'                   => (int) config('sin.nit'),
                ];

                try {
                    $respuesta = $servicio->registroEventoSignificativo(['SolicitudEventoSignificativo' => $solicitud]);
                    $arreglo = (array) $respuesta;
                    $datos = reset($arreglo);
                } catch (Throwable $e) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                }

                $codigo = $datos->codigoRecepcionEventoSignificativo ?? ($datos->codigoRecepcion ?? null);
                $ok = ($datos->transaccion ?? false) && $codigo;

                SinEventoPrueba::create([
                    'codigo_punto_venta' => $pv,
                    'codigo_motivo'      => $motivo,
                    'descripcion'        => $descripcion,
                    'cufd_evento'        => $cufd->cufd,
                    'fecha_inicio'       => $inicio,
                    'fecha_fin'          => $fin,
                    'estado'             => $ok ? SinEventoPrueba::REGISTRADO : SinEventoPrueba::RECHAZADO,
                    'codigo_recepcion'   => $codigo,
                    'mensajes'           => json_encode($datos->mensajesList ?? $datos, JSON_UNESCAPED_UNICODE),
                ]);

                $linea = sprintf('Motivo %d (%s) -> %s código: %s', $motivo, $descripcion, $ok ? 'REGISTRADO' : 'RECHAZADO', $codigo ?? '-');

                if ($ok) {
                    $correctos++;
                    $this->info($linea);
                } else {
                    $fallidos++;
                    $this->warn($linea . ' | ' . json_encode($datos->mensajesList ?? $datos, JSON_UNESCAPED_UNICODE));
                }
            }
        }

        $this->line("Registrados: {$correctos} | Con problemas: {$fallidos}");

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Inicio del próximo evento: justo después del anterior de este punto de venta y CUFD. */
    private function siguienteInicio(int $pv, string $cufd, Carbon $emision): Carbon
    {
        $ultimo = SinEventoPrueba::where('codigo_punto_venta', $pv)
            ->where('cufd_evento', $cufd)
            ->max('fecha_fin');

        $primero = $emision->copy()->addMinutes(2);

        if (! $ultimo) {
            return $primero;
        }

        $siguiente = Carbon::parse($ultimo)->addSeconds(self::SEPARACION_SEGUNDOS - self::DURACION_SEGUNDOS);

        return $siguiente->greaterThan($primero) ? $siguiente : $primero;
    }

    /** @return array<int, string> código => descripción, desde el catálogo sincronizado */
    private function descripciones(): array
    {
        $fila = SinCatalogo::where('catalogo', 'ParametricaEventosSignificativos')->first();
        $lista = [];

        foreach ((array) ($fila?->datos ?? []) as $valor) {
            if (is_array($valor)) {
                $lista = isset($valor['codigoClasificador']) ? [$valor] : $valor;
                break;
            }
        }

        $mapa = [];

        foreach ($lista as $item) {
            if (is_array($item) && isset($item['codigoClasificador'])) {
                $mapa[(int) $item['codigoClasificador']] = (string) ($item['descripcion'] ?? '');
            }
        }

        return $mapa;
    }
}
