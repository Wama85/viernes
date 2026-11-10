<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // XML tal como se envió al SIN (para el correo al cliente y los paquetes de contingencia).
            $table->longText('xml')->nullable()->after('codigo_excepcion');
            $table->dateTime('fecha_correo')->nullable()->after('xml');
            $table->string('correo_enviado_a', 500)->nullable()->after('fecha_correo');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['xml', 'fecha_correo', 'correo_enviado_a']);
        });
    }
};
