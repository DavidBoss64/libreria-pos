<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Almacen;
use RuntimeException;

class StockInsuficienteException extends RuntimeException
{
    public function __construct(int $almacenId, int $productoVarianteId, int $disponible, int $solicitado)
    {
        $almacenNombre = Almacen::find($almacenId)?->nombre ?? "#{$almacenId}";
        parent::__construct(
            "Stock insuficiente en el almacén {$almacenNombre} para la variante {$productoVarianteId}: " .
                "disponible {$disponible}, solicitado {$solicitado}."
        );
    }
}
