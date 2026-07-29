<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class StockInsuficienteException extends RuntimeException
{
    public function __construct(int $almacenId, int $productoVarianteId, int $disponible, int $solicitado)
    {
        parent::__construct(
            "Stock insuficiente en el almacén {$almacenId} para la variante {$productoVarianteId}: ".
            "disponible {$disponible}, solicitado {$solicitado}."
        );
    }
}
