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
    Schema::create('empresa', function (Blueprint $table) {
        $table->id('id_empresa');
        $table->string('nombre_empresa', 150);
        $table->string('propietario', 100);
        $table->string('carnet', 20);
        $table->string('nit', 20)->nullable();
        $table->string('direccion', 255);
        $table->string('ciudad_pais', 100)->nullable();
        $table->string('telefonos', 100);
        $table->string('correo', 100);
        $table->string('sitio_web', 100)->nullable();
        $table->text('cuentas_bancarias')->nullable();
        $table->string('formas_pago', 255)->nullable();
        $table->string('logo', 255)->nullable();
        $table->string('sello', 255)->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('empresa');
}
};
