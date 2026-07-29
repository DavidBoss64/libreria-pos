<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoMovimientoInventario: string implements HasColor, HasLabel
{
    case Ingreso = 'ingreso';
    case Salida = 'salida';
    case Ajuste = 'ajuste';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Salida => 'Salida',
            self::Ajuste => 'Ajuste',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ingreso => 'success',
            self::Salida => 'danger',
            self::Ajuste => 'warning',
        };
    }
}
