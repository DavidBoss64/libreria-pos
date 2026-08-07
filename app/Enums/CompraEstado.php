<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CompraEstado: string implements HasColor, HasLabel
{
    case Completado = 'completado';
    case Anulado = 'anulado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Completado => 'Completado',
            self::Anulado => 'Anulado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Completado => 'success',
            self::Anulado => 'danger',
        };
    }
}
