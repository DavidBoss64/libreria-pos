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
        Schema::create('traspasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_origen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('almacen_destino_id')->constrained('almacenes')->restrictOnDelete();
            $table->enum('estado', ['solicitado', 'preparando', 'en_transito', 'completado', 'cancelado'])
                ->default('solicitado');
            $table->foreignId('usuario_solicitante_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('usuario_procesador_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traspasos');
    }
};
