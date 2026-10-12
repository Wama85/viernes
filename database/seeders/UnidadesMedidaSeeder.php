<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnidadesMedidaSeeder extends Seeder
{
    public function run(): void
    {
        // Escribe en cada fila el codigoMedida que tienes en tu tabla antigua "medidas".
        $unidades = [
            [58, 'PIEZAS'],
            [57, 'UNIDAD (SERVICIOS)'],
            [62, 'UNIDAD (BIENES)'],
            [47, 'PAQUETE'],
        ];

        foreach ($unidades as [$codigoSin, $nombre]) {
            DB::table('unidades_medida')->insert([
                'codigo_sin' => $codigoSin,
                'nombre_unidad_medida' => $nombre,
                'unidad_medida_estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
