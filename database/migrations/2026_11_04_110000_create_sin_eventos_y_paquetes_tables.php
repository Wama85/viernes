<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eventos significativos registrados en el SIN (contingencia)
        Schema::create('sin_eventos', function (Blueprint $table) {
            $table->id('id_sin_evento');
            $table->unsignedSmallInteger('codigo_punto_venta')->default(0);
            $table->unsignedTinyInteger('codigo_motivo');
            $table->string('descripcion', 200);
            $table->string('cufd_evento', 200);
            $table->string('cufd', 200);
            $table->dateTime('fecha_inicio', 3);
            $table->dateTime('fecha_fin', 3);
            $table->string('estado', 20)->default('REGISTRADO'); // REGISTRADO | RECHAZADO
            $table->string('codigo_recepcion', 40)->nullable();
            $table->text('mensajes')->nullable();
            $table->timestamps();
        });

        // Paquetes de facturas fuera de línea enviados al SIN
        Schema::create('sin_paquetes', function (Blueprint $table) {
            $table->id('id_sin_paquete');
            $table->unsignedBigInteger('id_sin_evento');
            $table->unsignedSmallInteger('cantidad_facturas');
            $table->unsignedBigInteger('numero_inicio');
            $table->unsignedBigInteger('numero_fin');
            $table->string('hash_archivo', 64);
            $table->string('codigo_recepcion', 40)->nullable();
            $table->integer('codigo_estado')->nullable();
            $table->integer('codigo_estado_validacion')->nullable();
            $table->string('estado', 20)->default('ENVIADO'); // ENVIADO | VALIDADO | RECHAZADO
            $table->text('mensajes')->nullable();
            $table->timestamps();

            $table->foreign('id_sin_evento')->references('id_sin_evento')->on('sin_eventos');
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_sin_paquete')->nullable()->after('codigo_emision');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('id_sin_paquete');
        });
        Schema::dropIfExists('sin_paquetes');
        Schema::dropIfExists('sin_eventos');
    }
};
