<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdelantoSueldo;
use App\Models\User;

/**
 * Adelantos de sueldo son información financiera sensible de nómina — decisión
 * confirmada con el propietario: acceso exclusivo de `admin` (mismo criterio que
 * Sucursal/Almacen/User, gestión exclusiva de gerencia).
 */
class AdelantoSueldoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AdelantoSueldo $adelantoSueldo): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AdelantoSueldo $adelantoSueldo): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AdelantoSueldo $adelantoSueldo): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, AdelantoSueldo $adelantoSueldo): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, AdelantoSueldo $adelantoSueldo): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
