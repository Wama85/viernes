<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id('id_producto');
            $table->foreignId('id_producto_sin')
                  ->constrained('productos_sin', 'id_producto_sin');
            $table->foreignId('id_categoria')
                  ->constrained('categorias', 'id_categoria');
            $table->foreignId('id_unidad_medida')
                  ->constrained('unidades_medida', 'id_unidad_medida');
            $table->string('descripcion_producto', 500);
            $table->text('detalle')->nullable();
            $table->string('imagen', 255)->nullable();
            $table->decimal('costo_compra', 10, 2)->default(0);
            $table->decimal('precio_venta', 10, 2);
            $table->boolean('controla_stock')->default(true);
            $table->decimal('stock', 10, 2)->default(0);
            $table->date('fecha_vencimiento')->nullable();
            $table->unsignedTinyInteger('producto_estado')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
