<?php

namespace App\Modules\Sin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sin\Models\SinCatalogo;
use App\Modules\Sin\Models\SinCufd;
use App\Modules\Sin\Models\SinCuis;
use App\Modules\Sin\Services\SinSincronizacionService;
use Illuminate\Http\RedirectResponse;
use Throwable;

class SinSincronizacionController extends Controller
{
    public function index()
    {
        $guardados = SinCatalogo::all()->keyBy('catalogo');

        $catalogos = collect(SinSincronizacionService::METODOS)->map(function (string $metodo) use ($guardados) {
            $nombre = SinSincronizacionService::nombre($metodo);
            $fila = $guardados->get($nombre);

            return [
                'nombre'      => $nombre,
                'sincronizado' => $fila?->sincronizado_en,
                'registros'   => $fila ? SinSincronizacionService::contar($fila->datos) : null,
            ];
        });

        return view('sin.sincronizacion', [
            'catalogos'  => $catalogos,
            'cuis'       => SinCuis::actual(),
            'cufd'       => SinCufd::actual(),
            'resultados' => session('resultados_sincronizacion', []),
            'ambiente'   => (int) config('sin.ambiente'),
        ]);
    }

    public function sincronizar(SinSincronizacionService $servicio): RedirectResponse
    {
        set_time_limit(180);

        try {
            $resultados = $servicio->sincronizar();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo sincronizar: ' . $e->getMessage());
        }

        $fallidos = collect($resultados)->where('ok', false)->count();
        $total = count($resultados);

        return back()
            ->with('resultados_sincronizacion', $resultados)
            ->with(
                $fallidos ? 'error' : 'success',
                $fallidos
                    ? "Se sincronizaron " . ($total - $fallidos) . " de {$total} catálogos. Revisa los que fallaron."
                    : "Los {$total} catálogos se sincronizaron correctamente."
            );
    }
}
