<?php

declare(strict_types=1);

namespace App\Actions\Compras;

use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Enums\CompraEstado;
use App\Enums\TipoMovimientoInventario;
use App\Models\Compra;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AnularCompraAction
{
    /**
     * Revierte, línea por línea, el stock que una compra `completado` inyectó (una
     * `salida` de Kardex por cada línea, igual a lo que se registró como `ingreso`) y
     * marca la compra como `anulado`. Si alguna línea ya no tiene stock suficiente para
     * revertir (porque ese stock ya se vendió o se traspasó desde entonces),
     * `RegistrarMovimientoInventarioAction` lanza `StockInsuficienteException` — se deja
     * propagar sin capturar aquí, para que quien llame decida cómo mostrarlo (mismo
     * criterio que `AccionAjusteInventario`); la transacción revierte todo, nunca deja
     * la compra ni el stock a medias.
     */
    public function handle(Compra $compra, int $usuarioId): Compra
    {
        if ($compra->estado === CompraEstado::Anulado) {
            throw new RuntimeException("La compra #{$compra->id} ya está anulada.");
        }

        return DB::transaction(function () use ($compra, $usuarioId) {
            foreach ($compra->detalles as $detalle) {
                app(RegistrarMovimientoInventarioAction::class)->handle(
                    almacenId: $compra->almacen_id,
                    productoVarianteId: $detalle->producto_variante_id,
                    tipoMovimiento: TipoMovimientoInventario::Salida,
                    cantidad: $detalle->cantidad,
                    motivo: "Anulación de compra #{$compra->id}",
                    usuarioId: $usuarioId,
                    referenciaTipo: Compra::class,
                    referenciaId: $compra->id,
                );
            }

            $compra->update(['estado' => CompraEstado::Anulado]);

            return $compra->fresh('detalles');
        });
    }
}
