<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('cufd', 300)->nullable()->after('cuf');
            $table->string('leyenda', 300)->nullable()->after('cufd');
            $table->unsignedTinyInteger('codigo_ambiente')->nullable()->after('leyenda');
            $table->unsignedSmallInteger('codigo_punto_venta')->nullable()->after('codigo_ambiente');
            $table->unsignedSmallInteger('codigo_estado')->nullable()->after('codigo_recepcion');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['cufd', 'leyenda', 'codigo_ambiente', 'codigo_punto_venta', 'codigo_estado']);
        });
    }
};
