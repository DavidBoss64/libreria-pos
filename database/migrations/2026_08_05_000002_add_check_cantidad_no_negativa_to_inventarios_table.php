<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Red de seguridad de última línea (pendiente marcado en PLAN_DESARROLLO.md antes
     * de Fase 5): el invariante "cantidad nunca negativa" ya se garantiza en
     * RegistrarMovimientoInventarioAction, pero solo como disciplina de código —
     * este CHECK lo blinda también a nivel de base de datos.
     *
     * Solo aplica en Postgres (motor real): SQLite (usado únicamente por la suite de
     * tests en memoria, ver CLAUDE.md regla 17) no soporta agregar un CHECK constraint
     * sobre una tabla existente vía ALTER TABLE — mismo motivo por el que
     * lockForUpdate() real solo se verifica contra Postgres, no SQLite.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE inventarios ADD CONSTRAINT inventarios_cantidad_no_negativa CHECK (cantidad >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE inventarios DROP CONSTRAINT inventarios_cantidad_no_negativa');
    }
};
