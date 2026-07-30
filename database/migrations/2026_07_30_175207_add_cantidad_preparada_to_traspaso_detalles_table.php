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
        Schema::table('traspaso_detalles', function (Blueprint $table) {
            $table->integer('cantidad_preparada')->nullable()->after('cantidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traspaso_detalles', function (Blueprint $table) {
            $table->dropColumn('cantidad_preparada');
        });
    }
};
