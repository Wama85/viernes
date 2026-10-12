<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id('id_unidad_medida');
            $table->unsignedInteger('codigo_sin')->nullable()->unique();
            $table->string('nombre_unidad_medida', 100);
            $table->unsignedTinyInteger('unidad_medida_estado')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida');
    }
};
