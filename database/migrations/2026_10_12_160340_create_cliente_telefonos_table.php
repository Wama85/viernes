<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_telefonos', function (Blueprint $table) {
            $table->id('id_cliente_telefono');
            $table->foreignId('id_cliente')
                  ->constrained('clientes', 'id_cliente')
                  ->cascadeOnDelete();
            $table->string('telefono', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_telefonos');
    }
};
