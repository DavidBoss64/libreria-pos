<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Compra;
use App\Models\User;

class CompraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isAlmacenero();
    }

    public function view(User $user, Compra $compra): bool
    {
        return $user->isAdmin() || $user->isAlmacenero();
    }

    /**
     * Decisión confirmada con el propietario (sesión 2026-08-05): tanto el admin como
     * el Almacenero pueden registrar compras — el admin sin restricción de almacén
     * (elige a cuál va el reabastecimiento), el Almacenero limitado a los suyos
     * (`AislaPorAlmacen`, ya aplicado a nivel de Resource).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isAlmacenero();
    }

    /**
     * Una compra registrada no se edita — es un documento histórico, igual que Venta y
     * Traspaso. El único cambio posible es anularla, ver `anular()`.
     */
    public function update(User $user, Compra $compra): bool
    {
        return false;
    }

    /**
     * Habilidad custom (no es una de las 10 estándar): anular revierte stock ya
     * inyectado — restringido a `admin` únicamente, mismo criterio que el Ajuste
     * Manual de Inventario (operación sensible que deshace un movimiento de Kardex).
     */
    public function anular(User $user, Compra $compra): bool
    {
        return $user->isAdmin();
    }

    /**
     * Las compras son un registro de auditoría, igual que ventas y traspasos: nunca se
     * eliminan (tampoco tienen SoftDeletes en el esquema).
     */
    public function delete(User $user, Compra $compra): bool
    {
        return false;
    }

    public function restore(User $user, Compra $compra): bool
    {
        return false;
    }

    public function forceDelete(User $user, Compra $compra): bool
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
