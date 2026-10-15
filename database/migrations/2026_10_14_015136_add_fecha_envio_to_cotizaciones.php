<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiar restos de la primera versión, si existen
        if (Schema::hasColumn('cotizaciones', 'id_usuario_envio')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->dropForeign(['id_usuario_envio']);
            });
        }

        foreach (['canal_envio', 'id_usuario_envio', 'respuesta_cliente', 'fecha_respuesta'] as $columna) {
            if (Schema::hasColumn('cotizaciones', $columna)) {
                Schema::table('cotizaciones', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }

        if (Schema::hasColumn('cotizacion_detalles', 'aprobado_cliente')) {
            Schema::table('cotizacion_detalles', function (Blueprint $table) {
                $table->dropColumn('aprobado_cliente');
            });
        }

        // Lo único que realmente se necesita
        if (! Schema::hasColumn('cotizaciones', 'fecha_envio')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->dateTime('fecha_envio')->nullable()->after('observaciones');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cotizaciones', 'fecha_envio')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->dropColumn('fecha_envio');
            });
        }
    }
};
