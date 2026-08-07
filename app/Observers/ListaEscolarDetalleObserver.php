<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ListaEscolarDetalle;

/**
 * Mantiene `listas_escolares.precio_total_estimado` sincronizado con la suma real de sus
 * líneas — nunca se escribe a mano en el formulario (Paso 5.1, decisión confirmada con el
 * propietario). Efecto secundario simple de un solo registro directamente dependiente
 * (CLAUDE.md regla 2), mismo criterio que `SucursalObserver`/`ProductoObserver`.
 */
class ListaEscolarDetalleObserver
{
    public function created(ListaEscolarDetalle $detalle): void
    {
        $detalle->listaEscolar->recalcularPrecioTotalEstimado();
    }

    public function updated(ListaEscolarDetalle $detalle): void
    {
        $detalle->listaEscolar->recalcularPrecioTotalEstimado();
    }

    public function deleted(ListaEscolarDetalle $detalle): void
    {
        $detalle->listaEscolar->recalcularPrecioTotalEstimado();
    }
}
