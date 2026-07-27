<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SucursalRequeridaSegunRol implements ValidationRule
{
    public function __construct(
        private readonly ?UserRole $role,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->role !== UserRole::Admin && $value === null) {
            $fail('El usuario debe pertenecer a una sucursal, salvo que su rol sea Administrador.');
        }
    }
}
