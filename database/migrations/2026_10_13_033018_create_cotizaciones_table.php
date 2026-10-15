<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('cotizaciones', function (Blueprint $table) {
        $table->id('id_cotizacion');
        $table->unsignedSmallInteger('anio');
        $table->unsignedInteger('numero');
        $table->foreignId('id_cliente')->constrained('clientes', 'id_cliente');
        $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario');
        $table->dateTime('fecha_emision');
        $table->dateTime('fecha_vencimiento');
        $table->string('estado', 20)->default('PENDIENTE');
        $table->foreignId('id_aprobador')->nullable()->constrained('usuarios', 'id_usuario');
        $table->dateTime('fecha_aprobacion')->nullable();
        $table->text('observaciones')->nullable();
        $table->decimal('total', 12, 2)->default(0);
        $table->timestamps();

        $table->unique(['anio', 'numero']);
    });
}

public function down(): void
{
    Schema::dropIfExists('cotizaciones');
}


};
