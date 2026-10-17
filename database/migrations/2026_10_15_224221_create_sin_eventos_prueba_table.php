<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sin_eventos_prueba', function (Blueprint $table) {
            $table->id('id_sin_evento_prueba');
            $table->unsignedInteger('codigo_punto_venta')->default(0);
            $table->unsignedTinyInteger('codigo_motivo');
            $table->string('descripcion', 200);
            $table->string('cufd_evento', 200);
            $table->dateTime('fecha_inicio', 3);
            $table->dateTime('fecha_fin', 3);

            // REGISTRADO o RECHAZADO
            $table->string('estado', 20)->default('REGISTRADO');
            $table->string('codigo_recepcion', 60)->nullable();
            $table->text('mensajes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sin_eventos_prueba');
    }
};
