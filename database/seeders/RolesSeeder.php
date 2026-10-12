<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            [
                'nombre_rol' => 'Administrador',
                'rol_estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre_rol' => 'Vendedor',
                'rol_estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
