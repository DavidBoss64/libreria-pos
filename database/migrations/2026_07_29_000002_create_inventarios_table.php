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
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('producto_variante_id')->constrained('producto_variantes')->restrictOnDelete();
            $table->integer('cantidad')->default(0);
            $table->integer('cantidad_comprometida')->default(0);
            $table->integer('stock_minimo')->default(5);
            $table->timestamps();

            $table->unique(['almacen_id', 'producto_variante_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
