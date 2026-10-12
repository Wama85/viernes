<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('usuarios')->insert([
            'nombre' => 'Wilner Mena',
            'email' => 'wimen007@gmail.com',
            'password' => Hash::make('password123'),
            'id_rol' => 1, // Administrador
            'usuario_estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
