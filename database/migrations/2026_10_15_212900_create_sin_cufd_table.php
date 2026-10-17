<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sin_cufd', function (Blueprint $table) {
            $table->id('id_sin_cufd');
            $table->unsignedTinyInteger('codigo_ambiente');
            $table->unsignedInteger('codigo_sucursal')->default(0);
            $table->unsignedInteger('codigo_punto_venta')->default(0);
            $table->string('cufd', 255)->unique();
            $table->string('codigo_control', 100);
            $table->string('direccion', 500)->nullable();
            $table->dateTime('fecha_vigencia');
            $table->boolean('vigente')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sin_cufd');
    }
};
