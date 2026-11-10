<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // 1 = en línea, 2 = fuera de línea (evento significativo)
            $table->unsignedTinyInteger('codigo_emision')->default(1)->after('codigo_excepcion');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('codigo_emision');
        });
    }
};
