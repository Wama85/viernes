<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sin_paquetes_prueba', function (Blueprint $table) {
            $table->id('id_sin_paquete_prueba');
            $table->unsignedInteger('codigo_punto_venta')->default(0);
            $table->unsignedBigInteger('id_sin_evento_prueba')->index();
            $table->unsignedTinyInteger('codigo_motivo');
            $table->unsignedSmallInteger('cantidad_facturas');
            $table->unsignedInteger('numero_inicio');
            $table->unsignedInteger('numero_fin');
            $table->string('hash_archivo', 64);
            $table->string('codigo_recepcion', 100)->nullable();
            $table->integer('codigo_estado')->nullable();

            // ENVIADO, VALIDADO, RECHAZADO
            $table->string('estado', 20)->default('ENVIADO');
            $table->integer('codigo_estado_validacion')->nullable();
            $table->text('mensajes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sin_paquetes_prueba');
    }
};
