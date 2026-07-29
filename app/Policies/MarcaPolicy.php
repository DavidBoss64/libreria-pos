<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Marca;
use App\Models\User;

class MarcaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Marca $marca): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Marca $marca): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Marca $marca): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Marca $marca): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Marca $marca): bool
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
