<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aísla las queries de un Resource de Filament por la sucursal del usuario autenticado
 * (CLAUDE.md regla 9, LOGICA_NEGOCIO.md sección 1). El rol `admin` no se ve afectado.
 *
 * Se aplica en el Resource, no en el Modelo: un Global Scope de Eloquent afectaría también
 * Jobs, Artisan Commands y Services que corren sin usuario autenticado, con riesgo de
 * filtrar silenciosamente esas consultas o romperlas.
 *
 * Uso por defecto (modelo con columna `sucursal_id` directa, ej. `Venta`, `Cliente`):
 *   use AislaPorSucursal;
 *
 * Uso con relación indirecta (ej. `Inventario`/`Traspaso` vía `almacen_id` -> `almacenes.sucursal_id`):
 * sobreescribir `scopeQueryToSucursal()` en el propio Resource:
 *   protected static function scopeQueryToSucursal(Builder $query, ?int $sucursalId): Builder
 *   {
 *       return $query->whereHas('almacen', fn ($q) => $q->where('sucursal_id', $sucursalId));
 *   }
 */
trait AislaPorSucursal
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Filament::auth()->user();

        if ($user === null || $user->isAdmin()) {
            return $query;
        }

        return static::scopeQueryToSucursal($query, $user->sucursal_id);
    }

    protected static function scopeQueryToSucursal(Builder $query, ?int $sucursalId): Builder
    {
        return $query->where('sucursal_id', $sucursalId);
    }
}
