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
        Schema::create('producto_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('codigo_barras', 100)->nullable()->unique();
            $table->string('codigo_interno', 100)->unique();
            $table->jsonb('atributos')->nullable();
            $table->decimal('costo_real', 10, 2);
            $table->decimal('precio_venta_unidad', 10, 2);
            $table->decimal('precio_venta_docena', 10, 2);
            $table->decimal('precio_venta_mayor', 10, 2);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_variantes');
    }
};
