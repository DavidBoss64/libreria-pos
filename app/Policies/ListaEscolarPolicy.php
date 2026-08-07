<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ListaEscolar;
use App\Models\User;

class ListaEscolarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ListaEscolar $listaEscolar): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ListaEscolar $listaEscolar): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ListaEscolar $listaEscolar): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, ListaEscolar $listaEscolar): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, ListaEscolar $listaEscolar): bool
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
