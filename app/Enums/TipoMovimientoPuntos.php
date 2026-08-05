<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoMovimientoPuntos: string implements HasColor, HasLabel
{
    case Ganado = 'ganado';
    case Canjeado = 'canjeado';
    case Ajuste = 'ajuste';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ganado => 'Ganado',
            self::Canjeado => 'Canjeado',
            self::Ajuste => 'Ajuste',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ganado => 'success',
            self::Canjeado => 'warning',
            self::Ajuste => 'gray',
        };
    }
}
