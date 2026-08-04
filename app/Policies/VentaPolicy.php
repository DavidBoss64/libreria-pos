<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Venta;

class VentaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Venta $venta): bool
    {
        return true;
    }

    /**
     * Solo el Vendedor arma pre-ventas (Fase A del flujo, LOGICA_NEGOCIO.md sección 3).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isVendedor();
    }

    /**
     * Usado para "editar" una pre-venta mientras sigue `pendiente` — en la práctica,
     * la acción de tabla "Cancelar" (CLAUDE.md: "el Vendedor puede crear/editar
     * pre-ventas pendiente"). Cobrar es una habilidad aparte, ver `cobrar()`.
     */
    public function update(User $user, Venta $venta): bool
    {
        return $user->isAdmin() || $user->isVendedor();
    }

    /**
     * Habilidad custom (no es una de las 10 estándar): solo el Cajero cobra
     * (Fase B del flujo) — "el Cajero puede transicionarlas a completado y
     * registrar pago" (CLAUDE.md).
     */
    public function cobrar(User $user, Venta $venta): bool
    {
        return $user->isAdmin() || $user->isCajero();
    }

    /**
     * Las ventas son un registro de auditoría, igual que los traspasos: nunca se eliminan.
     */
    public function delete(User $user, Venta $venta): bool
    {
        return false;
    }

    public function restore(User $user, Venta $venta): bool
    {
        return false;
    }

    public function forceDelete(User $user, Venta $venta): bool
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
