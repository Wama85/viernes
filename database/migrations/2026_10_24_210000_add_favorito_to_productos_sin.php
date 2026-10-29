<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_sin', function (Blueprint $table) {
            $table->boolean('favorito')->default(false)->after('producto_sin_estado');
        });
    }

    public function down(): void
    {
        Schema::table('productos_sin', function (Blueprint $table) {
            $table->dropColumn('favorito');
        });
    }
};
