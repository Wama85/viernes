<?php

namespace App\Modules\Papelera\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UsoRegistros
{
    /**
     * Columnas de otras tablas que apuntan a este modelo, por dos vías:
     *  1) claves foráneas declaradas en la base de datos;
     *  2) columnas con el mismo nombre que la clave primaria (convención del proyecto:
     *     id_cliente, id_producto, id_categoria...), porque la base no declara claves foráneas.
     * Las tablas en $ignorarTablas (datos hijos que se borran junto con el registro) no cuentan.
     *
     * @return array<int, array{tabla: string, columna: string}>
     */
    public function referencias(Model $modelo, array $ignorarTablas = []): array
    {
        $tabla = $modelo->getTable();
        $clave = $modelo->getKeyName();

        $declaradas = DB::select(
            'SELECT TABLE_NAME AS tabla, COLUMN_NAME AS columna
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME = ?
                AND REFERENCED_COLUMN_NAME = ?',
            [$tabla, $clave]
        );

        $porNombre = DB::select(
            "SELECT c.TABLE_NAME AS tabla, c.COLUMN_NAME AS columna
               FROM information_schema.COLUMNS c
               JOIN information_schema.TABLES t
                 ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
                AND t.TABLE_NAME = c.TABLE_NAME
              WHERE c.TABLE_SCHEMA = DATABASE()
                AND t.TABLE_TYPE = 'BASE TABLE'
                AND c.COLUMN_NAME = ?
                AND c.TABLE_NAME <> ?",
            [$clave, $tabla]
        );

        return collect($declaradas)
            ->merge($porNombre)
            ->map(fn ($f) => ['tabla' => $f->tabla, 'columna' => $f->columna])
            ->unique(fn ($r) => $r['tabla'] . '.' . $r['columna'])
            ->reject(fn ($r) => in_array($r['tabla'], $ignorarTablas, true))
            ->values()
            ->all();
    }

    /**
     * Para cada id dado, en qué tablas se usa.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array<int, string>>  id => nombres de tabla
     */
    public function usos(Model $modelo, array $ids, array $ignorarTablas = []): array
    {
        $usos = [];

        if (empty($ids)) {
            return $usos;
        }

        foreach ($this->referencias($modelo, $ignorarTablas) as $ref) {
            $usados = DB::table($ref['tabla'])
                ->whereIn($ref['columna'], $ids)
                ->distinct()
                ->pluck($ref['columna']);

            foreach ($usados as $id) {
                $usos[(int) $id][] = $ref['tabla'];
            }
        }

        return $usos;
    }

    /** Nombre legible de una tabla para mostrar al usuario. */
    public function etiqueta(string $tabla): string
    {
        return match (true) {
            str_contains($tabla, 'cotizacion') => 'cotizaciones',
            str_contains($tabla, 'recibo')     => 'recibos',
            str_contains($tabla, 'factura')    => 'facturas',
            str_contains($tabla, 'movimiento') => 'movimientos de stock',
            str_contains($tabla, 'producto')   => 'productos',
            default                            => str_replace('_', ' ', $tabla),
        };
    }
}
