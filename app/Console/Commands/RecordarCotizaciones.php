<?php

namespace App\Console\Commands;

use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Usuarios\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RecordarCotizaciones extends Command
{
    protected $signature = 'cotizaciones:recordar {--dias=2 : Días de anticipación} {--simular : Solo muestra a quién se enviaría}';

    protected $description = 'Envía a cada usuario un resumen de sus cotizaciones aprobadas, sin recibo ni factura, que están por vencer.';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));

        $cotizaciones = Cotizacion::porVencer($dias)
            ->with(['cliente', 'creador'])
            ->orderBy('fecha_vencimiento')
            ->get();

        if ($cotizaciones->isEmpty()) {
            $this->info('No hay cotizaciones por vencer.');

            return self::SUCCESS;
        }

        $enviados = 0;

        // El administrador recibe todas; cada vendedor, solo las suyas.
        foreach (Usuario::where('usuario_estado', 1)->whereNotNull('email')->get() as $usuario) {
            $esAdmin = (int) $usuario->id_rol === 1;

            $lista = $esAdmin
                ? $cotizaciones
                : $cotizaciones->where('id_usuario', $usuario->id_usuario);

            if ($lista->isEmpty()) {
                continue;
            }

            $this->line("{$usuario->email}: {$lista->count()} cotización(es)");

            if ($this->option('simular')) {
                continue;
            }

            try {
                Mail::html(
                    $this->cuerpo($usuario, $lista, $dias, $esAdmin),
                    fn ($m) => $m->to($usuario->email)->subject('Cotizaciones por vencer (' . $lista->count() . ')')
                );
                $enviados++;
            } catch (Throwable $e) {
                Log::error("Recordatorio: no se pudo enviar a {$usuario->email}: " . $e->getMessage());
                $this->error("No se pudo enviar a {$usuario->email}: " . $e->getMessage());
            }
        }

        $this->info($this->option('simular') ? 'Simulación terminada.' : "Correos enviados: {$enviados}");

        return self::SUCCESS;
    }

    private function cuerpo(Usuario $usuario, Collection $lista, int $dias, bool $admin): string
    {
        $filas = '';

        foreach ($lista as $c) {
            $enlace = e(route('cotizaciones.show', $c));
            $numero = e($c->numero_completo);
            $cliente = e($c->cliente->razon_social ?? '');
            $vence = $c->fecha_vencimiento->format('d/m/Y H:i');
            $total = number_format((float) $c->total, 2);
            $aviso = $c->fueEnviada() ? '' : ' <span style="color:#b91c1c;font-weight:bold;">(sin enviar)</span>';
            $vendedor = $admin ? '<td style="padding:6px;border:1px solid #ddd;">' . e($c->creador->nombre ?? '') . '</td>' : '';

            $filas .= "<tr>"
                . "<td style=\"padding:6px;border:1px solid #ddd;\"><a href=\"{$enlace}\">{$numero}</a>{$aviso}</td>"
                . "<td style=\"padding:6px;border:1px solid #ddd;\">{$cliente}</td>"
                . $vendedor
                . "<td style=\"padding:6px;border:1px solid #ddd;\">{$vence}</td>"
                . "<td style=\"padding:6px;border:1px solid #ddd;text-align:right;\">Bs {$total}</td>"
                . "</tr>";
        }

        $columnaVendedor = $admin ? '<th style="padding:6px;border:1px solid #ddd;">Vendedor</th>' : '';
        $nombre = e($usuario->nombre);
        $sistema = e(config('app.name'));

        return "<div style=\"font-family:Arial,sans-serif;max-width:720px;margin:auto;color:#222;\">"
            . "<h2 style=\"color:#0c3082;\">Cotizaciones por vencer</h2>"
            . "<p>Hola {$nombre}, estas cotizaciones aprobadas vencen en los próximos {$dias} días y todavía no tienen ningún recibo ni factura:</p>"
            . "<table style=\"border-collapse:collapse;width:100%;font-size:14px;\">"
            . "<tr style=\"background:#f1f1f1;\"><th style=\"padding:6px;border:1px solid #ddd;\">Cotización</th>"
            . "<th style=\"padding:6px;border:1px solid #ddd;\">Cliente</th>{$columnaVendedor}"
            . "<th style=\"padding:6px;border:1px solid #ddd;\">Vence</th>"
            . "<th style=\"padding:6px;border:1px solid #ddd;\">Total</th></tr>"
            . $filas
            . "</table>"
            . "<p style=\"font-size:12px;color:#666;margin-top:16px;\">{$sistema}</p>"
            . "</div>";
    }
}
