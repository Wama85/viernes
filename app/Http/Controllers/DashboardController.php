<?php

namespace App\Http\Controllers;

use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Facturas\Models\Factura;
use App\Modules\Recibos\Models\Recibo;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $usuario = $request->user();
        $esAdmin = (int) $usuario->id_rol === 1;
        $inicioMes = now()->startOfMonth();

        // El vendedor solo ve lo suyo; el administrador ve todo
        $cotizaciones = Cotizacion::query()
            ->when(! $esAdmin, fn ($q) => $q->where('id_usuario', $usuario->id_usuario));

        $recibos = Recibo::query()
            ->when(! $esAdmin, function ($q) use ($usuario) {
                $q->whereHas('cotizacion', fn ($c) => $c->where('id_usuario', $usuario->id_usuario));
            });

        $tarjetas = [
            'del_mes' => (clone $cotizaciones)
                ->where('fecha_emision', '>=', $inicioMes)
                ->count(),

            // Aprobadas y vigentes que todavía no se enviaron al cliente
            'por_enviar' => (clone $cotizaciones)
                ->where('estado', Cotizacion::APROBADA)
                ->whereNull('fecha_envio')
                ->where('fecha_vencimiento', '>', now())
                ->count(),

            // Aprobadas que vencen en las próximas 24 horas
            'por_vencer' => (clone $cotizaciones)
                ->where('estado', Cotizacion::APROBADA)
                ->whereBetween('fecha_vencimiento', [now(), now()->addDay()])
                ->count(),

            // Recibos aprobados en el mes
            'ingresos_mes' => (float) (clone $recibos)
                ->where('estado', Recibo::APROBADO)
                ->where('fecha_emision', '>=', $inicioMes)
                ->sum('monto'),
        ];

        $pendientes = null;

        if ($esAdmin) {
            $pendientes = [
                'cotizaciones' => [
                    'total' => Cotizacion::where('estado', Cotizacion::PENDIENTE)->count(),
                    'lista' => Cotizacion::with('cliente')
                        ->where('estado', Cotizacion::PENDIENTE)
                        ->orderByDesc('id_cotizacion')->limit(5)->get(),
                ],
                'recibos' => [
                    'total' => Recibo::where('estado', Recibo::PENDIENTE)->count(),
                    'lista' => Recibo::with('cotizacion.cliente')
                        ->where('estado', Recibo::PENDIENTE)
                        ->orderByDesc('id_recibo')->limit(5)->get(),
                ],
                'facturas' => [
                    'total' => Factura::where('estado', Factura::PENDIENTE)->count(),
                    'lista' => Factura::where('estado', Factura::PENDIENTE)
                        ->orderByDesc('id_factura')->limit(5)->get(),
                ],
            ];
        }

        return view('dashboard', compact('tarjetas', 'pendientes', 'esAdmin'));
    }
}
