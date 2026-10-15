<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibo_detalles', function (Blueprint $table) {
            $table->foreignId('id_recibo')
                ->constrained('recibos', 'id_recibo')
                ->cascadeOnDelete();
            $table->foreignId('id_cotizacion_detalle')
                ->constrained('cotizacion_detalles', 'id_cotizacion_detalle')
                ->cascadeOnDelete();

            $table->primary(['id_recibo', 'id_cotizacion_detalle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibo_detalles');
    }
};
