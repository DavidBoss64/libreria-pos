<?php

declare(strict_types=1);

namespace App\Actions\Ventas;

use App\Models\Venta;

final readonly class ResultadoCierreVenta
{
    /**
     * @param  list<ItemRechazado>  $itemsRechazados
     */
    public function __construct(
        public Venta $venta,
        public array $itemsRechazados,
    ) {}
}
