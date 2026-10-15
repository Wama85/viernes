<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id('id_recibo');
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('numero');

            $table->foreignId('id_cotizacion')
                ->constrained('cotizaciones', 'id_cotizacion')
                ->restrictOnDelete();
            $table->foreignId('id_usuario')
                ->constrained('usuarios', 'id_usuario')
                ->restrictOnDelete();

            $table->dateTime('fecha_emision');
            $table->decimal('monto', 12, 2);
            $table->string('forma_pago', 20);
            $table->string('referencia_pago', 100)->nullable();

            $table->string('estado', 20)->default('PENDIENTE');
            $table->foreignId('id_aprobador')->nullable()
                ->constrained('usuarios', 'id_usuario')
                ->restrictOnDelete();
            $table->dateTime('fecha_aprobacion')->nullable();

            $table->timestamps();

            $table->unique(['anio', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};
