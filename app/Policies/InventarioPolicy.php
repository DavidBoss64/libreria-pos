<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Inventario;
use App\Models\User;

class InventarioPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Inventario $inventario): bool
    {
        return true;
    }

    /**
     * `inventarios` NUNCA se modifica directamente (CLAUDE.md regla 5): toda escritura
     * pasa por RegistrarMovimientoInventarioAction, no por un formulario de Filament.
     * Por eso estas habilidades son siempre falsas, incluso para el admin.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Inventario $inventario): bool
    {
        return false;
    }

    public function delete(User $user, Inventario $inventario): bool
    {
        return false;
    }

    public function restore(User $user, Inventario $inventario): bool
    {
        return false;
    }

    public function forceDelete(User $user, Inventario $inventario): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
