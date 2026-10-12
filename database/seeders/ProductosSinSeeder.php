<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductosSinSeeder extends Seeder
{
    public function run(): void
    {
        $productos = [
            [1001974, '4741100', 'terminales informáticas especializadas.'],
            [83151, '620901', 'SERVICIOS DE ALOJAMIENTO WEB (HOSTING)'],
            [1003917, '6202000', 'Consultoría de informática en instalaciones informáticas.'],
            [1003248, '4741100', 'Servicios de instalación y reparación de los equipos y máquinas incluidos en esta subclase, asociados a la venta.'],
            [1001978, '4741100', 'proyectores informáticos.'],
            [87290, '951000', 'SERVICIOS DE MANTENIMIENTO Y REPARACIÓN DE OTROS BIENES N.C.P.'],
            [62251, '476000', 'SERVICIOS DE COMERCIO AL POR MENOR DE LIBROS, REVISTAS, PERIÓDICOS Y ARTÍCULOS DE PAPELERÍA EN TIENDAS ESPECIALIZADAS'],
            [1002077, '4773100', '- Partes y accesorios de camaras y proyectores:'],
            [83161, '620901', 'SERVICIOS DE GESTIÓN DE REDES'],
            [872909, '951000', 'SERVICIOS DE MANTENIMIENTO Y REPARACIÓN DE OTROS BIENES N.C.P. IMPORTADO'],
        ];

        foreach ($productos as [$codigoSin, $actividad, $descripcion]) {
            DB::table('productos_sin')->insert([
                'codigo_producto_sin' => $codigoSin,
                'codigo_actividad' => $actividad,
                'descripcion_producto_sin' => $descripcion,
                'producto_sin_estado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
