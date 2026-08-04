<?php

declare(strict_types=1);

namespace App\Actions\Ventas;

use App\Actions\Inventario\AjustarComprometidoAction;
use App\Enums\VentaEstado;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelarPreventaAction
{
    /**
     * Cancela una pre-venta en estado 'pendiente' — manual, o desde el job que vence
     * pre-ventas abandonadas (ver LOGICA_NEGOCIO.md sección 3 y
     * CancelarPreventasVencidasCommand). Libera la `cantidad_comprometida` reservada
     * por cada línea. El estado persistido es 'anulado': es el único valor disponible
     * en el enum de `ventas.estado` (DATABASE.md) para una pre-venta que no se
     * completó — la nota de LOGICA_NEGOCIO.md que dice "cancelado" describe el mismo
     * concepto, no un estado distinto.
     */
    public function handle(Venta $venta): Venta
    {
        if ($venta->estado !== VentaEstado::Pendiente) {
            throw new RuntimeException("Solo se puede cancelar una pre-venta en estado 'pendiente'.");
        }

        return DB::transaction(function () use ($venta) {
            $almacenTienda = $venta->sucursal->almacenTienda();
            $ajustarComprometido = new AjustarComprometidoAction();

            // Defensivo: si el almacén 'tienda' de la sucursal ya no existe (caso límite,
            // no debería ocurrir en la práctica), se cancela igual sin bloquear al usuario;
            // solo no hay cantidad_comprometida que liberar.
            if ($almacenTienda !== null) {
                foreach ($venta->detalles as $detalle) {
                    $ajustarComprometido->handle($almacenTienda->id, $detalle->producto_variante_id, -$detalle->cantidad);
                }
            }

            $venta->update(['estado' => VentaEstado::Anulado]);

            return $venta->fresh(['detalles']);
        });
    }
}
