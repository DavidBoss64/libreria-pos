<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aísla las queries de un Resource de Filament por los almacenes asignados al usuario
 * autenticado (Almacenero) vía la tabla pivote `almacen_usuario` — NO por `sucursal_id`
 * (CLAUDE.md regla 9, LOGICA_NEGOCIO.md sección 2: modelo "hub and spoke"). El rol
 * `admin` no se ve afectado.
 *
 * Se aplica en el Resource, no en el Modelo, por la misma razón que `AislaPorSucursal`:
 * no romper Jobs/Commands/Services que corren sin usuario autenticado.
 *
 * Uso por defecto (modelo con columna `almacen_id` directa, ej. `Inventario`):
 *   use AislaPorAlmacen;
 *
 * Uso con relación indirecta o distinta (ej. `Traspaso`, que tiene `almacen_origen_id`
 * en vez de `almacen_id`): sobreescribir `scopeQueryToAlmacenes()` en el propio Resource:
 *   protected static function scopeQueryToAlmacenes(Builder $query, array $almacenIds): Builder
 *   {
 *       return $query->whereIn('almacen_origen_id', $almacenIds);
 *   }
 */
trait AislaPorAlmacen
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Filament::auth()->user();

        if ($user === null || $user->isAdmin()) {
            return $query;
        }

        return static::scopeQueryToAlmacenes($query, $user->almacenes()->pluck('almacenes.id')->all());
    }

    /**
     * @param  array<int, int>  $almacenIds
     */
    protected static function scopeQueryToAlmacenes(Builder $query, array $almacenIds): Builder
    {
        return $query->whereIn('almacen_id', $almacenIds);
    }
}
