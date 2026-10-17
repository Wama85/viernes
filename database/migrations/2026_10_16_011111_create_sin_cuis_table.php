<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sin_cuis', function (Blueprint $table) {
            $table->id('id_sin_cuis');
            $table->unsignedTinyInteger('codigo_ambiente');
            $table->unsignedInteger('codigo_sucursal')->default(0);
            $table->unsignedInteger('codigo_punto_venta')->default(0);
            $table->string('cuis', 50)->unique();
            $table->dateTime('fecha_vigencia');
            $table->boolean('vigente')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sin_cuis');
    }
};
