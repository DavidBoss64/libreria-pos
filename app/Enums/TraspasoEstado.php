<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TraspasoEstado: string implements HasColor, HasLabel
{
    case Solicitado = 'solicitado';
    case Preparando = 'preparando';
    case EnTransito = 'en_transito';
    case Completado = 'completado';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Solicitado => 'Solicitado',
            self::Preparando => 'Preparando',
            self::EnTransito => 'En tránsito',
            self::Completado => 'Completado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Solicitado => 'gray',
            self::Preparando => 'warning',
            self::EnTransito => 'info',
            self::Completado => 'success',
            self::Cancelado => 'danger',
        };
    }
}
