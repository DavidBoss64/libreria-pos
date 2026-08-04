<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VentaEstado: string implements HasColor, HasLabel
{
    case Pendiente = 'pendiente';
    case EsperandoPago = 'esperando_pago';
    case Completado = 'completado';
    case Anulado = 'anulado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EsperandoPago => 'Esperando pago',
            self::Completado => 'Completado',
            self::Anulado => 'Anulado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::EsperandoPago => 'warning',
            self::Completado => 'success',
            self::Anulado => 'danger',
        };
    }
}
