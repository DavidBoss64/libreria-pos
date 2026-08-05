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
        Schema::create('movimientos_puntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->restrictOnDelete();
            $table->enum('tipo', ['ganado', 'canjeado', 'ajuste']);
            $table->integer('puntos');
            $table->integer('saldo_despues');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_puntos');
    }
};
