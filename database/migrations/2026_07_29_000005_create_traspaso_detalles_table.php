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
        Schema::create('traspaso_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traspaso_id')->constrained('traspasos')->cascadeOnDelete();
            $table->foreignId('producto_variante_id')->constrained('producto_variantes')->restrictOnDelete();
            $table->integer('cantidad');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traspaso_detalles');
    }
};
