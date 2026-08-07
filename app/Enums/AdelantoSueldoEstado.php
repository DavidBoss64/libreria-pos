<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AdelantoSueldoEstado: string implements HasColor, HasLabel
{
    case PendienteDescuento = 'pendiente_descuento';
    case Descontado = 'descontado';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendienteDescuento => 'Pendiente de descuento',
            self::Descontado => 'Descontado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendienteDescuento => 'warning',
            self::Descontado => 'success',
        };
    }
}
