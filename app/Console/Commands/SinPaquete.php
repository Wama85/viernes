<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinEventoPrueba;
use App\Modules\Sin\Models\SinPaquetePrueba;
use App\Modules\Sin\Services\SinPaqueteService;
use App\Support\ConPuntoVenta;
use Illuminate\Console\Command;
use Throwable;

class SinPaquete extends Command
{
    use ConPuntoVenta;

    protected $signature = 'sin:paquete
                            {--motivo=0 : Motivo 1 a 7 (0 = recorre los 7 motivos)}
                            {--cantidad=1 : Paquetes logrados por motivo}
                            {--facturas=1 : Facturas por paquete (máximo 500)}
                            {--evento-de=0 : Toma el evento libre de este otro motivo (0 = del mismo motivo)}
                            {--pv= : Punto de venta (por defecto el del .env)}';

    protected $description = 'Envía paquetes de facturas fuera de línea al SIN (Etapa VI)';

    private const MAX_FALLAS_SEGUIDAS = 5;

    public function handle(SinPaqueteService $servicio): int
    {
        $pv = $this->puntoVenta();
        $this->line("Punto de venta: {$pv}");

        $motivos = (int) $this->option('motivo') > 0 ? [(int) $this->option('motivo')] : range(1, 7);
        $cantidad = max(1, (int) $this->option('cantidad'));
        $facturas = max(1, min(500, (int) $this->option('facturas')));
        $eventoDe = (int) $this->option('evento-de');

        $enviados = 0;
        $problemas = 0;

        foreach ($motivos as $motivo) {
            $logrados = 0;
            $fallasSeguidas = 0;
            $motivoEvento = $eventoDe > 0 ? $eventoDe : $motivo;

            while ($logrados < $cantidad) {
                // Solo eventos dentro del plazo del SIN: el paquete debe enviarse antes de 48 h del fin del evento.
                $evento = SinEventoPrueba::where('codigo_punto_venta', $pv)
                    ->where('codigo_motivo', $motivoEvento)
                    ->where('estado', SinEventoPrueba::REGISTRADO)
                    ->where('fecha_fin', '>=', now()->subHours(40))
                    ->whereNotIn('id_sin_evento_prueba', SinPaquetePrueba::query()
                        ->whereIn('estado', [SinPaquetePrueba::ENVIADO, SinPaquetePrueba::VALIDADO])
                        ->select('id_sin_evento_prueba'))
                    ->orderBy('id_sin_evento_prueba')
                    ->first();

                if (! $evento) {
                    $this->warn("Motivo {$motivo}: no quedan eventos libres del motivo {$motivoEvento} dentro del plazo de 48 h (faltan " . ($cantidad - $logrados) . "). Registra más con: php artisan sin:evento --pv={$pv} --motivo={$motivoEvento} --cantidad=N");
                    $problemas++;

                    break;
                }

                try {
                    $paquete = $servicio->enviar($evento, $facturas);
                } catch (Throwable $e) {
                    $this->error("Motivo {$motivo}: " . $e->getMessage());

                    return self::FAILURE;
                }

                $linea = sprintf(
                    'Motivo %d (evento del motivo %d) evento #%d -> %s (código %s) recepción: %s | %d facturas | %s',
                    $motivo,
                    $motivoEvento,
                    $evento->id_sin_evento_prueba,
                    $paquete->estado,
                    $paquete->codigo_estado ?? '-',
                    $paquete->codigo_recepcion ?? '-',
                    $paquete->cantidad_facturas,
                    $paquete->mensajes
                );

                if ($paquete->estado !== SinPaquetePrueba::RECHAZADO) {
                    $logrados++;
                    $enviados++;
                    $fallasSeguidas = 0;
                    $this->info($linea);

                    continue;
                }

                $this->warn($linea);
                $fallasSeguidas++;

                if (stripos((string) $paquete->mensajes, 'cafc') !== false) {
                    $this->warn("Motivo {$motivo}: el SIN exige un CAFC para este motivo. Se omite hasta tenerlo.");
                    $problemas++;

                    break;
                }

                if ($fallasSeguidas >= self::MAX_FALLAS_SEGUIDAS) {
                    $this->warn("Motivo {$motivo}: {$fallasSeguidas} fallas seguidas; me detengo. Vuelve a ejecutarlo en unos minutos.");
                    $problemas++;

                    break;
                }

                sleep(3);
            }
        }

        $this->line("Enviados: {$enviados} | Motivos con problemas: {$problemas}");

        return $problemas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
