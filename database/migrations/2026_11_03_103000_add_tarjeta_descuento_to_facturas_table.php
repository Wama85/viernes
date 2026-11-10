<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('descuento_adicional', 12, 2)->default(0)->after('monto_total');
            $table->string('numero_tarjeta', 16)->nullable()->after('forma_pago');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['descuento_adicional', 'numero_tarjeta']);
        });
    }
};
