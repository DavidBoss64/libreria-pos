<?php

declare(strict_types=1);

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
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedInteger('puntos_ganados')->nullable()->default(0)->after('estado');
            $table->unsignedInteger('puntos_utilizados')->nullable()->default(0)->after('puntos_ganados');
            $table->decimal('descuento_por_puntos', 10, 2)->nullable()->default(0)->after('puntos_utilizados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['puntos_ganados', 'puntos_utilizados', 'descuento_por_puntos']);
        });
    }
};
