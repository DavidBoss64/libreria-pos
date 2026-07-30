<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AlmacenTipo;
use App\Models\Sucursal;

class SucursalObserver
{
    /**
     * Toda Sucursal nueva debe nacer con su propio Almacén tipo `tienda` —
     * evita que quede sin punto de venta asignado por un error de configuración
     * manual (ver LOGICA_NEGOCIO.md sección 1 y PLAN_DESARROLLO.md Paso 3.5).
     */
    public function created(Sucursal $sucursal): void
    {
        $sucursal->almacenes()->create([
            'nombre' => 'Tienda ' . $sucursal->nombre,
            'tipo' => AlmacenTipo::Tienda,
            'estado' => true,
        ]);
    }

    /**
     * Al enviar una Sucursal a la papelera, sus almacenes la acompañan
     * (Paso 3.7). Solo se llega aquí si `SucursalPolicy`/el guard de la
     * Action ya confirmó que ninguno de sus almacenes tiene stock físico
     * (ver `Sucursal::tieneStockFisico()`), así que la cascada es segura.
     */
    public function deleted(Sucursal $sucursal): void
    {
        $sucursal->almacenes()->delete();
    }
}
