<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_sin', function (Blueprint $table) {
            $table->id('id_producto_sin');
            $table->unsignedBigInteger('codigo_producto_sin');
            $table->string('codigo_actividad', 10);
            $table->string('descripcion_producto_sin', 500);
            $table->unsignedTinyInteger('producto_sin_estado')->default(1);
            $table->timestamps();

            $table->unique(['codigo_producto_sin', 'codigo_actividad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_sin');
    }
};
