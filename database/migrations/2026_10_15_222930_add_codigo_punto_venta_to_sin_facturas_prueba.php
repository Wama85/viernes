<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sin_facturas_prueba', function (Blueprint $table) {
            $table->unsignedInteger('codigo_punto_venta')->default(0)->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('sin_facturas_prueba', function (Blueprint $table) {
            $table->dropColumn('codigo_punto_venta');
        });
    }
};
