<?php

declare(strict_types=1);

namespace App\Actions\Ventas;

use App\Actions\Inventario\AjustarComprometidoAction;
use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Enums\TipoMovimientoInventario;
use App\Enums\VentaEstado;
use App\Enums\VentaMetodoPago;
use App\Exceptions\StockInsuficienteException;
use App\Exceptions\VentaSinStockDisponibleException;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CerrarVentaAction
{
    /**
     * Fase B del flujo de Pre-venta (ver LOGICA_NEGOCIO.md sección 3): el Cajero cobra.
     * Descuenta stock real línea por línea con `lockForUpdate()` (vía
     * RegistrarMovimientoInventarioAction, reutilizando el mismo primitivo ya cubierto
     * por la prueba de concurrencia real de Fase 3 — InventarioConcurrenciaTest — no se
     * duplica un arnés de dos procesos aparte para esta Action, ver nota en
     * PLAN_DESARROLLO.md Paso 4.2).
     *
     * Si una línea específica ya no tiene stock suficiente (venta concurrente), esa
     * línea se RECHAZA (se excluye de la venta, se borra su detalle) y el resto
     * continúa — "no se cancela toda la venta automáticamente" (LOGICA_NEGOCIO.md
     * sección 3B). Solo falla por completo si NINGUNA línea pudo venderse.
     *
     * @param  array{usuario_id: int, metodo_pago: VentaMetodoPago, referencia_pago?: ?string}  $pago
     */
    public function handle(Venta $venta, array $pago): ResultadoCierreVenta
    {
        if ($venta->estado !== VentaEstado::Pendiente) {
            throw new RuntimeException("Solo se puede cerrar una venta en estado 'pendiente'.");
        }

        $metodoPago = $pago['metodo_pago'];
        $referenciaPago = $pago['referencia_pago'] ?? null;

        // Pago digital obligatorio con referencia, para conciliación — LOGICA_NEGOCIO.md sección 3B.
        if ($metodoPago !== VentaMetodoPago::Efectivo && ($referenciaPago === null || trim($referenciaPago) === '')) {
            throw new InvalidArgumentException('El pago digital (transferencia/tarjeta/QR) requiere registrar referencia_pago.');
        }

        return DB::transaction(function () use ($venta, $pago, $metodoPago, $referenciaPago) {
            $almacenTienda = $venta->sucursal->almacenTienda();

            if ($almacenTienda === null) {
                throw new RuntimeException("La sucursal {$venta->sucursal->nombre} no tiene un almacén tipo 'tienda' configurado.");
            }

            $registrarMovimiento = new RegistrarMovimientoInventarioAction();
            $ajustarComprometido = new AjustarComprometidoAction();

            $total = '0.00';
            $itemsRechazados = [];

            foreach ($venta->detalles as $detalle) {
                try {
                    $registrarMovimiento->handle(
                        almacenId: $almacenTienda->id,
                        productoVarianteId: $detalle->producto_variante_id,
                        tipoMovimiento: TipoMovimientoInventario::Salida,
                        cantidad: $detalle->cantidad,
                        motivo: "Venta {$venta->numero_ticket}",
                        usuarioId: $pago['usuario_id'],
                        referenciaTipo: Venta::class,
                        referenciaId: $venta->id,
                    );
                } catch (StockInsuficienteException $e) {
                    $itemsRechazados[] = new ItemRechazado(
                        $detalle->producto_variante_id,
                        $detalle->cantidad,
                        $e->getMessage(),
                    );

                    $ajustarComprometido->handle($almacenTienda->id, $detalle->producto_variante_id, -$detalle->cantidad);
                    $detalle->delete();

                    continue;
                }

                $ajustarComprometido->handle($almacenTienda->id, $detalle->producto_variante_id, -$detalle->cantidad);
                $total = bcadd($total, (string) $detalle->subtotal, 2);
            }

            if ($venta->detalles()->count() === 0) {
                throw new VentaSinStockDisponibleException($venta->id);
            }

            $venta->update([
                'total' => $total,
                'metodo_pago' => $metodoPago,
                'referencia_pago' => $referenciaPago,
                'usuario_id' => $pago['usuario_id'],
                'estado' => VentaEstado::Completado,
            ]);

            return new ResultadoCierreVenta($venta->fresh(['detalles']), $itemsRechazados);
        });
    }
}
