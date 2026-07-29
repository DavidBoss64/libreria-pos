<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Traspaso;
use App\Models\User;

class TraspasoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Traspaso $traspaso): bool
    {
        return true;
    }

    /**
     * Solo el Vendedor solicita traspasos al depósito (LOGICA_NEGOCIO.md sección 5).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isVendedor();
    }

    /**
     * Usado para las transiciones de estado (preparar/despachar/completar/cancelar)
     * en el panel `almacen`: solo el Almacenero que despacha el traspaso.
     */
    public function update(User $user, Traspaso $traspaso): bool
    {
        return $user->isAdmin() || $user->isAlmacenero();
    }

    /**
     * Los traspasos son un registro de auditoría, igual que el Kardex: nunca se eliminan.
     */
    public function delete(User $user, Traspaso $traspaso): bool
    {
        return false;
    }

    public function restore(User $user, Traspaso $traspaso): bool
    {
        return false;
    }

    public function forceDelete(User $user, Traspaso $traspaso): bool
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
