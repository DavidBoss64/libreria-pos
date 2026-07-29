<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoPrecioAplicado;

final readonly class PrecioCalculado
{
    public function __construct(
        public TipoPrecioAplicado $tipo,
        public string $precioUnitario,
        public string $subtotal,
    ) {}
}
