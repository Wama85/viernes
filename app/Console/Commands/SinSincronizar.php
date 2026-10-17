<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Models\SinCuis;
use App\Modules\Sin\Services\SinCliente;
use App\Support\ConPuntoVenta;
use Illuminate\Console\Command;
use Throwable;

class SinSincronizar extends Command
{
    use ConPuntoVenta;

    protected $signature = 'sin:sincronizar
                            {--veces=1 : Cuántas veces repetir la ronda completa}
                            {--pv= : Punto de venta (por defecto el del .env)}';

    protected $description = 'Sincroniza los catálogos del SIN y los guarda (Etapa II)';

    private const METODOS = [
        'sincronizarActividades',
        'sincronizarFechaHora',
        'sincronizarListaActividadesDocumentoSector',
        'sincronizarListaLeyendasFactura',
        'sincronizarListaMensajesServicios',
        'sincronizarListaProductosServicios',
        'sincronizarParametricaEventosSignificativos',
        'sincronizarParametricaMotivoAnulacion',
        'sincronizarParametricaPaisOrigen',
        'sincronizarParametricaTipoDocumentoIdentidad',
        'sincronizarParametricaTipoDocumentoSector',
        'sincronizarParametricaTipoEmision',
        'sincronizarParametricaTipoHabitacion',
        'sincronizarParametricaTipoMetodoPago',
        'sincronizarParametricaTipoMoneda',
        'sincronizarParametricaTipoPuntoVenta',
        'sincronizarParametricaTiposFactura',
        'sincronizarParametricaUnidadMedida',
    ];

    public function handle(SinCliente $sin): int
    {
        $pv = $this->puntoVenta();
        $this->line("Punto de venta: {$pv}");

        $cuis = SinCuis::actual();

        if (! $cuis) {
            $this->error("No hay un CUIS vigente para el punto de venta {$pv}. Ejecuta primero: php artisan sin:cuis --pv={$pv}");

            return self::FAILURE;
        }

        $solicitud = $sin->solicitudBase();
        unset($solicitud['codigoModalidad']);
        $solicitud['cuis'] = $cuis->cuis;

        $servicio = $sin->servicio('FacturacionSincronizacion');
        $veces = max(1, (int) $this->option('veces'));
        $correctos = 0;
        $fallidos = 0;

        for ($ronda = 1; $ronda <= $veces; $ronda++) {
            foreach (self::METODOS as $metodo) {
                try {
                    $respuesta = $servicio->{$metodo}(['SolicitudSincronizacion' => $solicitud]);
                    $arreglo = (array) $respuesta;
                    $datos = reset($arreglo);
                    $ok = (bool) ($datos->transaccion ?? false);
                } catch (Throwable $e) {
                    $ok = false;
                    $datos = null;

                    if ($ronda === 1) {
                        $this->error("{$metodo}: " . $e->getMessage());
                    }
                }

                if ($ok) {
                    $correctos++;

                    // Se guarda el resultado de la primera ronda
                    if ($ronda === 1) {
                        SinCatalogo::updateOrCreate(
                            ['catalogo' => substr($metodo, strlen('sincronizar'))],
                            [
                                'datos'           => json_decode(json_encode($datos), true),
                                'sincronizado_en' => now(),
                            ]
                        );
                        $this->info("OK    {$metodo}");
                    }
                } else {
                    $fallidos++;

                    if ($ronda === 1 && $datos) {
                        $this->warn("FALLÓ {$metodo}: " . json_encode($datos->mensajesList ?? $datos, JSON_UNESCAPED_UNICODE));
                    }
                }
            }
        }

        $this->line("Rondas: {$veces} | Correctos: {$correctos} | Fallidos: {$fallidos}");

        return $fallidos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
