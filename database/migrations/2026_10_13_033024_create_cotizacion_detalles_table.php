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
    Schema::create('cotizacion_detalles', function (Blueprint $table) {
        $table->id('id_cotizacion_detalle');
        $table->foreignId('id_cotizacion')
              ->constrained('cotizaciones', 'id_cotizacion')
              ->cascadeOnDelete();
        $table->foreignId('id_producto')->constrained('productos', 'id_producto');
        $table->foreignId('id_unidad_medida')->constrained('unidades_medida', 'id_unidad_medida');
        $table->string('descripcion', 500);
        $table->text('descripcion_adicional')->nullable();
        $table->decimal('cantidad', 10, 2);
        $table->decimal('precio_unitario', 10, 2);
        $table->decimal('descuento', 10, 2)->default(0);
        $table->decimal('subtotal', 12, 2);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('cotizacion_detalles');
}
};
