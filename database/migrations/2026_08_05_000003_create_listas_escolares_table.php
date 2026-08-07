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
        Schema::create('listas_escolares', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_plantilla', 255);
            $table->string('colegio', 255)->nullable();
            $table->decimal('precio_total_estimado', 10, 2)->default(0);
            $table->boolean('es_plantilla')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listas_escolares');
    }
};
