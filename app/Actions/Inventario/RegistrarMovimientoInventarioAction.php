<?php

declare(strict_types=1);

namespace App\Actions\Inventario;

use App\Enums\TipoMovimientoInventario;
use App\Exceptions\StockInsuficienteException;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrarMovimientoInventarioAction
{
    /**
     * Único punto de entrada para modificar `inventarios`: nunca se debe hacer
     * `Inventario::update()` fuera de esta clase (Kardex obligatorio, ver CLAUDE.md regla 5).
     *
     * Para 'ingreso'/'salida', $cantidad es siempre positiva y la dirección la determina
     * $tipoMovimiento. Para 'ajuste', $cantidad es un delta con signo (puede subir o bajar
     * el stock, ej. merma o corrección de conteo físico).
     */
    public function handle(
        int $almacenId,
        int $productoVarianteId,
        TipoMovimientoInventario $tipoMovimiento,
        int $cantidad,
        string $motivo,
        int $usuarioId,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
    ): MovimientoInventario {
        $delta = match ($tipoMovimiento) {
            TipoMovimientoInventario::Ingreso => $this->cantidadPositivaOFalla($cantidad),
            TipoMovimientoInventario::Salida => -$this->cantidadPositivaOFalla($cantidad),
            TipoMovimientoInventario::Ajuste => $this->cantidadDistintaDeCeroOFalla($cantidad),
        };

        return DB::transaction(function () use ($almacenId, $productoVarianteId, $tipoMovimiento, $delta, $motivo, $usuarioId, $referenciaTipo, $referenciaId) {
            // upsert con update no-op: garantiza la fila sin condición de carrera frente a otro
            // proceso creándola al mismo tiempo (la restricción única de inventarios lo protege).
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

            $saldoDespues = $inventario->cantidad + $delta;

            if ($saldoDespues < 0) {
                throw new StockInsuficienteException($almacenId, $productoVarianteId, $inventario->cantidad, abs($delta));
            }

            $inventario->update(['cantidad' => $saldoDespues]);

            return MovimientoInventario::create([
                'almacen_id' => $almacenId,
                'producto_variante_id' => $productoVarianteId,
                'tipo_movimiento' => $tipoMovimiento,
                'cantidad' => $delta,
                'saldo_despues' => $saldoDespues,
                'motivo' => $motivo,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'usuario_id' => $usuarioId,
            ]);
        });
    }

    private function cantidadPositivaOFalla(int $cantidad): int
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad de un ingreso/salida debe ser mayor a cero.');
        }

        return $cantidad;
    }

    private function cantidadDistintaDeCeroOFalla(int $cantidad): int
    {
        if ($cantidad === 0) {
            throw new InvalidArgumentException('La cantidad de un ajuste no puede ser cero.');
        }

        return $cantidad;
    }
}
