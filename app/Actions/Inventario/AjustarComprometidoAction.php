<?php

declare(strict_types=1);

namespace App\Actions\Inventario;

use App\Models\Inventario;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AjustarComprometidoAction
{
    /**
     * Ajusta `inventarios.cantidad_comprometida` — el indicador NO bloqueante de
     * unidades presentes en pre-ventas activas (ver DATABASE.md sección 3: "no
     * bloquea la venta, es solo un indicador visual"). A diferencia de
     * `RegistrarMovimientoInventarioAction`, esto no es un movimiento real de stock:
     * no genera Kardex, solo el contador que ve el Vendedor junto al stock físico.
     */
    public function handle(int $almacenId, int $productoVarianteId, int $delta): void
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('El delta de cantidad_comprometida no puede ser cero.');
        }

        DB::transaction(function () use ($almacenId, $productoVarianteId, $delta) {
            // Mismo patrón upsert no-op + lockForUpdate que RegistrarMovimientoInventarioAction:
            // garantiza la fila sin condición de carrera frente a otro proceso creándola a la vez.
            Inventario::upsert(
                [[
                    'almacen_id' => $almacenId,
                    'producto_variante_id' => $productoVarianteId,
                    'cantidad' => 0,
                    'cantidad_comprometida' => 0,
                    'stock_minimo' => 5,
                ]],
                ['almacen_id', 'producto_variante_id'],
                ['almacen_id'],
            );

            $inventario = Inventario::where('almacen_id', $almacenId)
                ->where('producto_variante_id', $productoVarianteId)
                ->lockForUpdate()
                ->firstOrFail();

            $nuevoValor = $inventario->cantidad_comprometida + $delta;

            if ($nuevoValor < 0) {
                throw new RuntimeException(
                    "cantidad_comprometida no puede quedar negativa (almacén {$almacenId}, variante {$productoVarianteId})."
                );
            }

            $inventario->update(['cantidad_comprometida' => $nuevoValor]);
        });
    }
}
