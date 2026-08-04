<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VentaMetodoPago: string implements HasColor, HasLabel
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Tarjeta = 'tarjeta';
    case Qr = 'qr';

    public function getLabel(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Tarjeta => 'Tarjeta',
            self::Qr => 'QR',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Efectivo => 'success',
            self::Transferencia => 'info',
            self::Tarjeta => 'primary',
            self::Qr => 'warning',
        };
    }
}
