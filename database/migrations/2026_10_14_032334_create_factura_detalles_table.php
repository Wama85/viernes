<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factura_detalles', function (Blueprint $table) {
            $table->foreignId('id_factura')
                ->constrained('facturas', 'id_factura')
                ->cascadeOnDelete();
            $table->foreignId('id_cotizacion_detalle')
                ->constrained('cotizacion_detalles', 'id_cotizacion_detalle')
                ->restrictOnDelete();

            $table->primary(['id_factura', 'id_cotizacion_detalle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_detalles');
    }
};
