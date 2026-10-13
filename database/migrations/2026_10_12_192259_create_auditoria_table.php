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
    Schema::create('auditoria', function (Blueprint $table) {
        $table->id('id_auditoria');
        $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario');
        $table->string('accion', 30);
        $table->string('tabla_afectada', 60)->nullable();
        $table->unsignedBigInteger('id_registro')->nullable();
        $table->json('datos_anteriores')->nullable();
        $table->json('datos_nuevos')->nullable();
        $table->string('ip', 45)->nullable();
        $table->timestamp('fecha_hora')->useCurrent();

        $table->index(['tabla_afectada', 'id_registro']);
    });
}

public function down(): void
{
    Schema::dropIfExists('auditoria');
}
};
