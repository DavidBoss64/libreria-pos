<?php

declare(strict_types=1);

namespace App\Actions\Traspasos;

use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TraspasoEstado;
use App\Models\Traspaso;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompletarTraspasoAction
{
    /**
     * Completa un Traspaso en estado 'en_transito': saca stock del almacén origen
     * e ingresa la misma cantidad al almacén destino, línea por línea, dentro de
     * una única transacción (si una línea falla por stock insuficiente, se revierte todo).
     */
    public function handle(Traspaso $traspaso, int $usuarioProcesadorId): Traspaso
    {
        if ($traspaso->estado !== TraspasoEstado::EnTransito) {
            throw new RuntimeException("Solo se puede completar un traspaso en estado 'en_transito'.");
        }

        return DB::transaction(function () use ($traspaso, $usuarioProcesadorId) {
            $registrarMovimiento = new RegistrarMovimientoInventarioAction();

            foreach ($traspaso->detalles as $detalle) {
                $registrarMovimiento->handle(
                    almacenId: $traspaso->almacen_origen_id,
                    productoVarianteId: $detalle->producto_variante_id,
                    tipoMovimiento: TipoMovimientoInventario::Salida,
                    cantidad: $detalle->cantidad,
                    motivo: "Traspaso #{$traspaso->id}: salida a almacén {$traspaso->almacen_destino_id}",
                    usuarioId: $usuarioProcesadorId,
                    referenciaTipo: Traspaso::class,
                    referenciaId: $traspaso->id,
                );

                $registrarMovimiento->handle(
                    almacenId: $traspaso->almacen_destino_id,
                    productoVarianteId: $detalle->producto_variante_id,
                    tipoMovimiento: TipoMovimientoInventario::Ingreso,
                    cantidad: $detalle->cantidad,
                    motivo: "Traspaso #{$traspaso->id}: ingreso desde almacén {$traspaso->almacen_origen_id}",
                    usuarioId: $usuarioProcesadorId,
                    referenciaTipo: Traspaso::class,
                    referenciaId: $traspaso->id,
                );
            }

            $traspaso->update([
                'estado' => TraspasoEstado::Completado,
                'usuario_procesador_id' => $usuarioProcesadorId,
            ]);

            return $traspaso->fresh(['detalles']);
        });
    }
}
