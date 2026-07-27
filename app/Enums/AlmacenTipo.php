<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AlmacenTipo: string implements HasColor, HasLabel
{
    case Tienda = 'tienda';
    case Deposito = 'deposito';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tienda => 'Tienda',
            self::Deposito => 'Depósito',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Tienda => 'success',
            self::Deposito => 'gray',
        };
    }
}
