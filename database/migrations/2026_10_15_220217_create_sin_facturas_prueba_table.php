<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sin_facturas_prueba', function (Blueprint $table) {
            $table->id('id_sin_factura_prueba');
            $table->unsignedInteger('numero')->unique();
            $table->string('cuf', 120)->unique();
            $table->string('cufd', 200);
            $table->dateTime('fecha_emision', 3);
            $table->decimal('monto_total', 12, 2);
            $table->longText('xml');

            // GENERADA, VALIDADA, RECHAZADA, ANULADA
            $table->string('estado', 20)->default('GENERADA');
            $table->integer('codigo_estado')->nullable();
            $table->string('codigo_recepcion', 100)->nullable();
            $table->text('mensajes')->nullable();
            $table->unsignedTinyInteger('motivo_anulacion')->nullable();
            $table->unsignedTinyInteger('reversiones')->default(0);
            $table->dateTime('enviada_en')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sin_facturas_prueba');
    }
};
