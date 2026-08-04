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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            // Nullable: se genera recién después del insert, a partir del propio `id`
            // autoincremental (ver CLAUDE.md regla 11) — nunca contando+1 en la aplicación.
            $table->string('numero_ticket', 20)->nullable()->unique();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->string('cliente_temporal', 100)->nullable();
            $table->decimal('total', 10, 2);
            $table->enum('metodo_pago', ['efectivo', 'transferencia', 'tarjeta', 'qr'])->nullable();
            $table->string('referencia_pago')->nullable();
            $table->enum('origen', ['pos', 'ecommerce'])->default('pos');
            $table->enum('estado', ['pendiente', 'esperando_pago', 'completado', 'anulado'])->index();
            $table->timestamp('expira_en')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
