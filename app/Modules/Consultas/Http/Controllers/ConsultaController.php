<?php

namespace App\Modules\Consultas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cotizaciones\Models\Cotizacion;
use App\Modules\Facturas\Models\Factura;
use App\Modules\Recibos\Models\Recibo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class ConsultaController extends Controller
{
    private const TIPOS = ['cotizaciones', 'recibos', 'facturas'];

    private const ESTADOS = [
        'cotizaciones' => ['PENDIENTE', 'APROBADA', 'FACTURADA', 'ANULADA'],
        'recibos'      => ['PENDIENTE', 'APROBADO', 'ANULADO'],
        'facturas'     => ['PENDIENTE', 'APROBADA', 'ANULADA'],
    ];

    public function index(Request $request)
    {
        $usuario = $request->user();
        $esAdmin = (int) $usuario->id_rol === 1;

        $tipo = $request->query('tipo', 'cotizaciones');
        if (! in_array($tipo, self::TIPOS, true)) {
            $tipo = 'cotizaciones';
        }

        $validador = Validator::make($request->query(), [
            'q'         => ['nullable', 'string', 'min:2', 'max:100', "regex:/^[\p{L}\p{N}\s\.\,\-&'\"\(\)\/]+$/u"],
            'documento' => ['nullable', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9\-\.\s]+$/'],
            'numero'    => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\s]*\d[A-Za-z0-9\-\s]*$/'],
            'estado'    => ['nullable', 'in:' . implode(',', self::ESTADOS[$tipo])],
            'desde'     => ['nullable', 'date', 'before_or_equal:today'],
            'hasta'     => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:desde'],
            'vendedor'  => ['nullable', 'integer', 'exists:usuarios,id_usuario'],
        ], [
            'q.min'                  => 'La institución debe tener al menos 2 letras.',
            'q.max'                  => 'La institución no puede pasar de 100 caracteres.',
            'q.regex'                => 'La institución tiene caracteres no permitidos.',
            'documento.min'          => 'El NIT/CI debe tener al menos 3 caracteres.',
            'documento.regex'        => 'El NIT/CI solo admite letras, números, guion y punto.',
            'numero.regex'           => 'El número debe llevar al menos un dígito (ej: COT-2026-0007 o 7).',
            'estado.in'              => 'El estado elegido no es válido.',
            'desde.date'             => 'La fecha "desde" no es válida.',
            'desde.before_or_equal'  => 'La fecha "desde" no puede ser futura.',
            'hasta.date'             => 'La fecha "hasta" no es válida.',
            'hasta.before_or_equal'  => 'La fecha "hasta" no puede ser futura.',
            'hasta.after_or_equal'   => 'La fecha "hasta" no puede ser anterior a "desde".',
            'vendedor.exists'        => 'El vendedor elegido no existe.',
        ]);

        $vendedores = $esAdmin
            ? DB::table('usuarios')->orderBy('nombre')->get(['id_usuario', 'nombre'])
            : collect();

        // Datos inválidos: se muestra la pantalla con los errores y sin resultados.
        if ($validador->fails()) {
            $f = ['q' => '', 'documento' => '', 'numero' => '', 'estado' => null, 'desde' => null, 'hasta' => null, 'vendedor' => null];
            foreach (['q', 'documento', 'numero'] as $campo) {
                $f[$campo] = trim((string) $request->query($campo));
            }
            foreach (['estado', 'desde', 'hasta'] as $campo) {
                $f[$campo] = $request->query($campo);
            }
            $f['vendedor'] = $esAdmin ? $request->query('vendedor') : null;

            return view('consultas.index', [
                'tipo'       => $tipo,
                'f'          => $f,
                'estados'    => self::ESTADOS[$tipo],
                'resultados' => new LengthAwarePaginator([], 0, 15),
                'vendedores' => $vendedores,
                'esAdmin'    => $esAdmin,
            ])->withErrors($validador);
        }

        $f = [
            'q'         => trim((string) $request->query('q')),
            'documento' => trim((string) $request->query('documento')),
            'numero'    => trim((string) $request->query('numero')),
            'estado'    => $request->query('estado'),
            'desde'     => $request->query('desde'),
            'hasta'     => $request->query('hasta'),
            'vendedor'  => $esAdmin ? $request->query('vendedor') : null,
        ];

        $resultados = match ($tipo) {
            'recibos'  => $this->recibos($f, $usuario, $esAdmin),
            'facturas' => $this->facturas($f, $usuario, $esAdmin),
            default    => $this->cotizaciones($f, $usuario, $esAdmin),
        };

        return view('consultas.index', [
            'tipo'       => $tipo,
            'f'          => $f,
            'estados'    => self::ESTADOS[$tipo],
            'resultados' => $resultados,
            'vendedores' => $vendedores,
            'esAdmin'    => $esAdmin,
        ]);
    }

    // ------------------------------------------------------------------

    private function cotizaciones(array $f, $usuario, bool $esAdmin)
    {
        [$anio, $numero] = $this->separarNumero($f['numero']);

        return Cotizacion::with(['cliente', 'creador'])
            ->withCount([
                'recibos as recibos_activos'   => fn ($q) => $q->where('recibos.estado', '!=', Recibo::ANULADO),
                'facturas as facturas_activas' => fn ($q) => $q->where('facturas.estado', '!=', Factura::ANULADA),
            ])
            ->when(! $esAdmin, fn ($q) => $q->where('id_usuario', $usuario->id_usuario))
            ->when($esAdmin && $f['vendedor'], fn ($q) => $q->where('id_usuario', $f['vendedor']))
            ->when($f['q'] !== '', fn ($q) => $q->whereHas('cliente', fn ($c) => $this->palabras($c, 'razon_social', $f['q'])))
            ->when($f['documento'] !== '', fn ($q) => $q->whereHas('cliente', fn ($c) => $c->where('documento_identidad', 'like', '%' . $this->like($f['documento']) . '%')))
            ->when($numero !== null, fn ($q) => $q->where('numero', $numero))
            ->when($anio !== null, fn ($q) => $q->where('anio', $anio))
            ->when($f['estado'], fn ($q) => $q->where('estado', $f['estado']))
            ->when($f['desde'], fn ($q) => $q->whereDate('fecha_emision', '>=', $f['desde']))
            ->when($f['hasta'], fn ($q) => $q->whereDate('fecha_emision', '<=', $f['hasta']))
            ->orderByDesc('anio')
            ->orderByDesc('numero')
            ->paginate(15)
            ->withQueryString();
    }

    private function recibos(array $f, $usuario, bool $esAdmin)
    {
        [$anio, $numero] = $this->separarNumero($f['numero']);

        return Recibo::with(['cotizacion.cliente'])
            ->when(! $esAdmin, fn ($q) => $q->whereHas('cotizacion', fn ($c) => $c->where('id_usuario', $usuario->id_usuario)))
            ->when($esAdmin && $f['vendedor'], fn ($q) => $q->whereHas('cotizacion', fn ($c) => $c->where('id_usuario', $f['vendedor'])))
            ->when($f['q'] !== '', fn ($q) => $q->whereHas('cotizacion.cliente', fn ($c) => $this->palabras($c, 'razon_social', $f['q'])))
            ->when($f['documento'] !== '', fn ($q) => $q->whereHas('cotizacion.cliente', fn ($c) => $c->where('documento_identidad', 'like', '%' . $this->like($f['documento']) . '%')))
            ->when($numero !== null, fn ($q) => $q->where('numero', $numero))
            ->when($anio !== null, fn ($q) => $q->where('anio', $anio))
            ->when($f['estado'], fn ($q) => $q->where('estado', $f['estado']))
            ->when($f['desde'], fn ($q) => $q->whereDate('fecha_emision', '>=', $f['desde']))
            ->when($f['hasta'], fn ($q) => $q->whereDate('fecha_emision', '<=', $f['hasta']))
            ->orderByDesc('anio')
            ->orderByDesc('numero')
            ->paginate(15)
            ->withQueryString();
    }

    private function facturas(array $f, $usuario, bool $esAdmin)
    {
        // Las facturas no se numeran por año: se usa solo el último número escrito.
        [, $numero] = $this->separarNumero($f['numero']);

        return Factura::with(['cotizacion'])
            ->when(! $esAdmin, fn ($q) => $q->whereHas('cotizacion', fn ($c) => $c->where('id_usuario', $usuario->id_usuario)))
            ->when($esAdmin && $f['vendedor'], fn ($q) => $q->whereHas('cotizacion', fn ($c) => $c->where('id_usuario', $f['vendedor'])))
            // La factura guarda congelados los datos del cliente al momento de emitirla.
            ->when($f['q'] !== '', fn ($q) => $this->palabras($q, 'cliente_razon_social', $f['q']))
            ->when($f['documento'] !== '', fn ($q) => $q->where('cliente_documento', 'like', '%' . $this->like($f['documento']) . '%'))
            ->when($numero !== null, fn ($q) => $q->where('numero', $numero))
            ->when($f['estado'], fn ($q) => $q->where('estado', $f['estado']))
            ->when($f['desde'], fn ($q) => $q->whereDate('fecha_emision', '>=', $f['desde']))
            ->when($f['hasta'], fn ($q) => $q->whereDate('fecha_emision', '<=', $f['hasta']))
            ->orderByDesc('id_factura')
            ->paginate(15)
            ->withQueryString();
    }

    // ------------------------------------------------------------------

    /** Cada palabra escrita debe aparecer en el campo ("colegio san" encuentra "Colegio Nacional San Andrés"). */
    private function palabras($query, string $columna, string $texto)
    {
        foreach (preg_split('/\s+/', $texto, -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            $query->where($columna, 'like', '%' . $this->like($palabra) . '%');
        }

        return $query;
    }

    private function like(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $texto);
    }

    /**
     * Acepta "COT-2026-0007", "2026-7" o solo "7".
     * @return array{0: ?int, 1: ?int} [año, número]
     */
    private function separarNumero(string $texto): array
    {
        preg_match_all('/\d+/', $texto, $m);
        $partes = $m[0];

        if (empty($partes)) {
            return [null, null];
        }

        $ultimo = end($partes);

        // Un número gigante no existe: no devuelve nada en vez de desbordar.
        $numero = strlen($ultimo) > 9 ? 0 : (int) $ultimo;

        if (count($partes) >= 2 && strlen($partes[0]) === 4) {
            return [(int) $partes[0], $numero];
        }

        return [null, $numero];
    }
}
