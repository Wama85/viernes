<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TiposDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['codigo_sin' => 1, 'nombre_tipo_documento' => 'CI - Cédula de identidad'],
            ['codigo_sin' => 2, 'nombre_tipo_documento' => 'CEX - Cédula de identidad de extranjero'],
            ['codigo_sin' => 3, 'nombre_tipo_documento' => 'PAS - Pasaporte'],
            ['codigo_sin' => 4, 'nombre_tipo_documento' => 'OD - Otro documento de identidad'],
            ['codigo_sin' => 5, 'nombre_tipo_documento' => 'NIT - Número de identificación tributaria'],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipos_documento')->insert($tipo + [
                'tipo_documento_estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
