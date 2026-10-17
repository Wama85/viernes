<?php

namespace App\Console\Commands;

use App\Modules\Sin\Models\SinCatalogo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SinEnlazar extends Command
{
    protected $signature = 'sin:enlazar';
    protected $description = 'Enlaza productos, unidades y tipos de documento con los catálogos del SIN';

    public function handle(): int
    {
        $this->productos();
        $this->unidades();
        $this->tiposDocumento();

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------

    private function productos(): void
    {
        $this->info('== Productos y servicios del SIN ==');

        $items = $this->lista('ListaProductosServicios');

        if (empty($items)) {
            $this->warn('El catálogo de productos está vacío o tiene otra estructura.');

            return;
        }

        $ahora = now();
        $filas = [];

        foreach ($items as $i) {
            $filas[] = [
                'codigo_producto_sin'      => (int) $i['codigoProducto'],
                'codigo_actividad'         => (string) $i['codigoActividad'],
                'descripcion_producto_sin' => mb_substr((string) $i['descripcionProducto'], 0, 500),
                'producto_sin_estado'      => 1,
                'created_at'               => $ahora,
                'updated_at'               => $ahora,
            ];
        }

        foreach (array_chunk($filas, 500) as $bloque) {
            DB::table('productos_sin')->upsert(
                $bloque,
                ['codigo_producto_sin', 'codigo_actividad'],
                ['descripcion_producto_sin', 'producto_sin_estado', 'updated_at']
            );
        }

        $this->line('Importados/actualizados: ' . count($filas));

        // Registros locales que ya no están en el catálogo (no se tocan)
        $enSin = collect($filas)->map(fn ($f) => $f['codigo_producto_sin'] . '|' . $f['codigo_actividad'])->flip();

        $huerfanos = DB::table('productos_sin')->get()
            ->filter(fn ($p) => ! isset($enSin[$p->codigo_producto_sin . '|' . $p->codigo_actividad]));

        if ($huerfanos->isNotEmpty()) {
            $this->warn('Registros locales que no existen en el SIN (no se modificaron):');
            $this->table(
                ['Código', 'Actividad', 'Descripción'],
                $huerfanos->map(fn ($p) => [$p->codigo_producto_sin, $p->codigo_actividad, mb_substr($p->descripcion_producto_sin, 0, 70)])->all()
            );
        }
    }

    private function unidades(): void
    {
        $this->info('== Unidades de medida ==');

        $items = $this->lista('ParametricaUnidadMedida');

        if (empty($items)) {
            $this->warn('El catálogo de unidades está vacío o tiene otra estructura.');

            return;
        }

        $mapa = [];
        foreach ($items as $i) {
            $mapa[$this->normalizar($i['descripcion'])] = $i['codigoClasificador'];
        }

        $filas = [];
        foreach (DB::table('unidades_medida')->orderBy('id_unidad_medida')->get() as $u) {
            $sugerido = $mapa[$this->normalizar($u->nombre_unidad_medida)] ?? null;

            if ($sugerido !== null && $u->codigo_sin === null) {
                DB::table('unidades_medida')
                    ->where('id_unidad_medida', $u->id_unidad_medida)
                    ->update(['codigo_sin' => $sugerido, 'updated_at' => now()]);
            }

            $filas[] = [
                $u->id_unidad_medida,
                $u->nombre_unidad_medida,
                $u->codigo_sin ?? '—',
                $sugerido ?? 'SIN COINCIDENCIA',
            ];
        }

        $this->table(['Id', 'Unidad local', 'Código antes', 'Código SIN'], $filas);

        $this->line('Las unidades que declaraste ante el SIN, con su código:');
        foreach (['UNIDAD (BIENES)', 'PIEZAS', 'UNIDAD (SERVICIOS)'] as $nombre) {
            $this->line('  ' . $nombre . ' => ' . ($mapa[$this->normalizar($nombre)] ?? 'no encontrada'));
        }
    }

    private function tiposDocumento(): void
    {
        $this->info('== Tipos de documento de identidad ==');

        $items = $this->lista('ParametricaTipoDocumentoIdentidad');

        $this->line('Catálogo del SIN:');
        $this->table(
            ['Código', 'Descripción'],
            array_map(fn ($i) => [$i['codigoClasificador'], $i['descripcion']], $items)
        );

        $this->line('Tus tipos de documento:');
        $this->table(
            ['Id', 'codigo_sin', 'Nombre'],
            DB::table('tipos_documento')->orderBy('id_tipo_documento')->get()
                ->map(fn ($t) => [$t->id_tipo_documento, $t->codigo_sin, $t->nombre_tipo_documento])->all()
        );
    }

    // ---------------------------------------------------------------

    /** Devuelve la lista de un catálogo guardado, siempre como arreglo de elementos. */
    private function lista(string $catalogo): array
    {
        $datos = SinCatalogo::where('catalogo', $catalogo)->first()?->datos ?? [];
        $lista = $datos['listaCodigos'] ?? [];

        if (empty($lista)) {
            $this->warn("Claves encontradas en {$catalogo}: " . implode(', ', array_keys($datos)));

            return [];
        }

        // Si el SIN devuelve un solo elemento, llega como objeto y no como lista
        return array_is_list($lista) ? $lista : [$lista];
    }

    private function normalizar(string $texto): string
    {
        return mb_strtoupper(trim(Str::ascii($texto)));
    }
}
