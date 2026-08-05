<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configuracion_negocio', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('umbral_mayor')->default(24);
            $table->unsignedInteger('soles_por_punto')->default(30);
            $table->decimal('valor_por_punto', 10, 2)->default(0.30);
            $table->timestamps();
        });

        // Fila única (id = 1) — no es un catálogo, es la configuración editable del negocio.
        DB::table('configuracion_negocio')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_negocio');
    }
};
