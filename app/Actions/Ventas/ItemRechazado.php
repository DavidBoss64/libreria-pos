<?php

declare(strict_types=1);

namespace App\Actions\Ventas;

/**
 * Una línea de venta que no pudo cobrarse por falta de stock al momento del cierre
 * (venta concurrente) — ver CerrarVentaAction. Informativo para que el Cajero decida
 * cómo resolverlo con el cliente (LOGICA_NEGOCIO.md sección 3B).
 */
final readonly class ItemRechazado
{
    public function __construct(
        public int $productoVarianteId,
        public int $cantidadSolicitada,
        public string $motivo,
    ) {}
}
