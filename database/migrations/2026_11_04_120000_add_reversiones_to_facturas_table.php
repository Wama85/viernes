<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Motivo con el que se anuló en el SIN y cuántas veces se revirtió esa anulación (el SIN permite una sola).
            $table->unsignedTinyInteger('motivo_anulacion')->nullable()->after('id_sin_paquete');
            $table->unsignedTinyInteger('reversiones')->default(0)->after('motivo_anulacion');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['motivo_anulacion', 'reversiones']);
        });
    }
};
