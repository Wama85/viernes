<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_correos', function (Blueprint $table) {
            $table->id('id_cliente_correo');
            $table->foreignId('id_cliente')
                  ->constrained('clientes', 'id_cliente')
                  ->cascadeOnDelete();
            $table->string('correo', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_correos');
    }
};
