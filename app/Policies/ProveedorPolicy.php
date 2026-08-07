<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Proveedor;
use App\Models\User;

class ProveedorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Proveedor $proveedor): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Proveedor $proveedor): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Proveedor $proveedor): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Proveedor $proveedor): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Proveedor $proveedor): bool
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
