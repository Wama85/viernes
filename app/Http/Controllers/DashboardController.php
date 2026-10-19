<?php

namespace App\Http\Controllers;

use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Facturas\Models\Factura;
use App\Modules\Productos\Models\Producto;
use App\Modules\Recibos\Models\Recibo;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Días de anticipación del aviso de vencimiento. */
    private const DIAS_AVISO = 2;

    /** Días hacia atrás que se muestran las vencidas sin recibo ni factura. */
    private const DIAS_VENCIDAS = 7;

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

        $porVencer = (clone $cotizaciones)->porVencer(self::DIAS_AVISO);
        $vencidas = (clone $cotizaciones)->vencidasRecientes(self::DIAS_VENCIDAS);

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

            // Aprobadas, sin recibo ni factura, que vencen pronto
            'por_vencer' => (clone $porVencer)->count(),

            // Recibos aprobados en el mes
            'ingresos_mes' => (float) (clone $recibos)
                ->where('estado', Recibo::APROBADO)
                ->where('fecha_emision', '>=', $inicioMes)
                ->sum('monto'),
        ];

        $recordatorios = [
            'dias_aviso'    => self::DIAS_AVISO,
            'dias_vencidas' => self::DIAS_VENCIDAS,
            'por_vencer'    => [
                'total' => (clone $porVencer)->count(),
                'lista' => (clone $porVencer)->with(['cliente', 'creador'])
                    ->orderBy('fecha_vencimiento')->limit(8)->get(),
            ],
            'vencidas'      => [
                'total' => (clone $vencidas)->count(),
                'lista' => (clone $vencidas)->with(['cliente', 'creador'])
                    ->orderByDesc('fecha_vencimiento')->limit(8)->get(),
            ],
        ];

        // Productos agotados o en/bajo su stock mínimo
        $productosAlerta = Producto::query()
            ->where('producto_estado', 1)
            ->where('controla_stock', true)
            ->whereColumn('stock', '<=', 'stock_minimo');

        $stockAlertas = [
            'total' => (clone $productosAlerta)->count(),
            'lista' => (clone $productosAlerta)->orderBy('stock')->limit(6)->get(),
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

        return view('dashboard', compact('tarjetas', 'pendientes', 'esAdmin', 'stockAlertas', 'recordatorios'));
    }
}
