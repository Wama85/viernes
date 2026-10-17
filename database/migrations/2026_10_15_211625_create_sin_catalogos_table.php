<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sin_catalogos', function (Blueprint $table) {
            $table->id('id_sin_catalogo');
            $table->string('catalogo', 80)->unique();
            $table->longText('datos');
            $table->dateTime('sincronizado_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sin_catalogos');
    }
};
