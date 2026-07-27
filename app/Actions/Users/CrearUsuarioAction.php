<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\SucursalRequeridaSegunRol;
use Illuminate\Support\Facades\Validator;

class CrearUsuarioAction
{
    /**
     * Crea un usuario validando la regla de jerarquía: todo usuario debe
     * pertenecer a una Sucursal, salvo que su rol sea Administrador.
     *
     * @param  array{nombres: string, apellidos: string, email: string, password: string, role: UserRole, sucursal_id?: int|null, is_active?: bool}  $datos
     */
    public function handle(array $datos): User
    {
        Validator::make($datos, [
            'sucursal_id' => [new SucursalRequeridaSegunRol($datos['role'])],
        ])->validate();

        return User::create($datos);
    }
}
