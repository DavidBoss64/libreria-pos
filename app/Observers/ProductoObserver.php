<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Producto;

class ProductoObserver
{
    /**
     * Al enviar un Producto a la papelera, sus variantes lo acompañan —
     * evita que una "Variante" siga apareciendo activa (en pickers de venta,
     * traspasos, etc.) bajo un Producto que ya no existe para el catálogo
     * (Paso 3.7). No se cascada la restauración: el admin restaura variantes
     * puntuales desde el RelationManager si lo necesita, con control total
     * fila por fila (ya lo tiene, ver VariantesRelationManager).
     */
    public function deleted(Producto $producto): void
    {
        $producto->variantes()->delete();
    }
}
