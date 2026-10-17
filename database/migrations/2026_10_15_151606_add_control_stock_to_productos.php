<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('stock_minimo', 12, 2)->default(0)->after('stock');
        });

        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id('id_movimiento_stock');

            $table->foreignId('id_producto')
                ->constrained('productos', 'id_producto')
                ->restrictOnDelete();

            // ENTRADA, SALIDA, DEVOLUCION, AJUSTE
            $table->string('tipo', 20);

            // Con signo: positivo suma, negativo resta
            $table->decimal('cantidad', 12, 2);
            $table->decimal('stock_anterior', 12, 2);
            $table->decimal('stock_posterior', 12, 2);

            // Documento que originó el movimiento (RECIBO o FACTURA)
            $table->string('tipo_documento', 20)->nullable();
            $table->unsignedBigInteger('id_documento')->nullable();

            $table->string('motivo', 255)->nullable();

            // true cuando una SALIDA ya fue devuelta (anulación o edición)
            $table->boolean('revertido')->default(false);

            $table->foreignId('id_usuario')->nullable()
                ->constrained('usuarios', 'id_usuario')
                ->nullOnDelete();

            $table->dateTime('fecha_hora');

            $table->index(['tipo_documento', 'id_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('stock_minimo');
        });
    }
};
