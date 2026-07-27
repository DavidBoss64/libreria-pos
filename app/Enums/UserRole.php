<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Cajero = 'cajero';
    case Almacenero = 'almacenero';
    case Vendedor = 'vendedor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cajero => 'Cajero',
            self::Almacenero => 'Almacenero',
            self::Vendedor => 'Vendedor',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Cajero => 'success',
            self::Almacenero => 'warning',
            self::Vendedor => 'info',
        };
    }
}
