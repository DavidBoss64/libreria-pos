<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MovimientoInventario;
use App\Models\User;

class MovimientoInventarioPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MovimientoInventario $movimientoInventario): bool
    {
        return true;
    }

    /**
     * `movimientos_inventario` es un Kardex inmutable (CLAUDE.md regla 5): se
     * escribe únicamente desde RegistrarMovimientoInventarioAction, nunca desde
     * un formulario de Filament. Por eso estas habilidades son siempre falsas,
     * incluso para el admin.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MovimientoInventario $movimientoInventario): bool
    {
        return false;
    }

    public function delete(User $user, MovimientoInventario $movimientoInventario): bool
    {
        return false;
    }

    public function restore(User $user, MovimientoInventario $movimientoInventario): bool
    {
        return false;
    }

    public function forceDelete(User $user, MovimientoInventario $movimientoInventario): bool
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
