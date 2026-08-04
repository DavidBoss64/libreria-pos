<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class VentaSinStockDisponibleException extends RuntimeException
{
    public function __construct(int $ventaId)
    {
        parent::__construct(
            "La venta #{$ventaId} no pudo cerrarse: ninguna línea tenía stock disponible al momento del cobro."
        );
    }
}
