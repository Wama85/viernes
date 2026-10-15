<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id('id_factura');

            // Consecutivo único; se asigna al aprobar
            $table->unsignedInteger('numero')->nullable()->unique();

            $table->foreignId('id_cotizacion')
                ->constrained('cotizaciones', 'id_cotizacion')
                ->restrictOnDelete();
            $table->foreignId('id_usuario')
                ->constrained('usuarios', 'id_usuario')
                ->restrictOnDelete();

            $table->dateTime('fecha_emision');

            // Datos del cliente congelados al emitir
            $table->string('cliente_razon_social', 200);
            $table->unsignedBigInteger('cliente_id_tipo_documento')->nullable();
            $table->string('cliente_documento', 30);
            $table->string('cliente_complemento', 5)->nullable();
            $table->string('cliente_correo', 150)->nullable();

            $table->decimal('monto_total', 12, 2);
            $table->string('forma_pago', 20);

            // Estado interno: PENDIENTE, APROBADA, ANULADA
            $table->string('estado', 20)->default('PENDIENTE');
            $table->foreignId('id_aprobador')->nullable()
                ->constrained('usuarios', 'id_usuario')
                ->restrictOnDelete();
            $table->dateTime('fecha_aprobacion')->nullable();

            // Estado frente al SIN: NO_ENVIADA, VALIDADA, RECHAZADA
            $table->string('estado_sin', 20)->default('NO_ENVIADA');
            $table->string('cuf', 100)->nullable();
            $table->string('codigo_recepcion', 100)->nullable();
            $table->dateTime('fecha_envio_sin')->nullable();
            $table->text('mensaje_sin')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
