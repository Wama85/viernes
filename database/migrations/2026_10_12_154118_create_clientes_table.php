<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id('id_cliente');
            $table->foreignId('id_tipo_documento')
                  ->constrained('tipos_documento', 'id_tipo_documento');
            $table->string('documento_identidad', 20);
            $table->string('complemento', 10)->nullable();
            $table->string('razon_social', 100);
            $table->string('nombre_responsable', 100)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->unsignedTinyInteger('cliente_estado')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
