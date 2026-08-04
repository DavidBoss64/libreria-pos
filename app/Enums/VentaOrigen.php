<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VentaOrigen: string implements HasColor, HasLabel
{
    case Pos = 'pos';
    case Ecommerce = 'ecommerce';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pos => 'Tienda física',
            self::Ecommerce => 'E-commerce',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pos => 'gray',
            self::Ecommerce => 'info',
        };
    }
}
