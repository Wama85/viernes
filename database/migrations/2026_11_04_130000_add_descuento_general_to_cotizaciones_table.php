<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            // Descuento sobre el total de la cotización, en Bs (si se escribió en %, ya viene convertido).
            $table->decimal('descuento_general', 10, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn('descuento_general');
        });
    }
};
