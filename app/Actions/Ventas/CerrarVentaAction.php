<?php

declare(strict_types=1);

namespace App\Actions\Ventas;

use App\Actions\Inventario\AjustarComprometidoAction;
use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Actions\Puntos\RegistrarMovimientoPuntosAction;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TipoMovimientoPuntos;
use App\Enums\VentaEstado;
use App\Enums\VentaMetodoPago;
use App\Exceptions\StockInsuficienteException;
use App\Exceptions\VentaSinStockDisponibleException;
use App\Models\ConfiguracionNegocio;
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
     * Fidelización por puntos (LOGICA_NEGOCIO.md sección 9, Paso 4.5): si la venta tiene
     * cliente_id, el Cajero puede indicar cuántos puntos canjea (`puntos_utilizados` en
     * $pago) — nunca el Vendedor. El descuento resultante ya queda reflejado en el
     * `total` final, y los puntos ganados se calculan sobre ese mismo total final.
     *
     * @param  array{usuario_id: int, metodo_pago: VentaMetodoPago, referencia_pago?: ?string, puntos_utilizados?: int}  $pago
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

            $registrarMovimiento = new RegistrarMovimientoInventarioAction;
            $ajustarComprometido = new AjustarComprometidoAction;

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

            [$totalFinal, $puntosGanados, $puntosUtilizados, $descuentoPorPuntos] = $this->aplicarFidelizacion(
                $venta,
                $total,
                (int) ($pago['puntos_utilizados'] ?? 0),
                $pago['usuario_id'],
            );

            $venta->update([
                'total' => $totalFinal,
                'metodo_pago' => $metodoPago,
                'referencia_pago' => $referenciaPago,
                'usuario_id' => $pago['usuario_id'],
                'estado' => VentaEstado::Completado,
                'puntos_ganados' => $puntosGanados,
                'puntos_utilizados' => $puntosUtilizados,
                'descuento_por_puntos' => $descuentoPorPuntos,
            ]);


            return new ResultadoCierreVenta($venta->fresh(['detalles']), $itemsRechazados);
        });
    }

    /**
     * Solo aplica si la venta tiene `cliente_id` — un `cliente_temporal` no acumula ni
     * puede canjear (LOGICA_NEGOCIO.md sección 9). Los puntos ganados se calculan sobre
     * el total YA descontado por el canje: `DATABASE.md` aclara que `descuento_por_puntos`
     * queda "reflejado en total", así que "el total de la venta" de la regla de
     * acumulación es el monto final, no el bruto antes del canje.
     *
     * @return array{0: string, 1: int, 2: int, 3: string} [totalFinal, puntosGanados, puntosUtilizados, descuentoPorPuntos]
     */
    private function aplicarFidelizacion(Venta $venta, string $total, int $puntosUtilizadosSolicitados, int $usuarioId): array
    {
        if ($venta->cliente_id === null) {
            if ($puntosUtilizadosSolicitados > 0) {
                throw new InvalidArgumentException('Solo un cliente registrado puede canjear puntos.');
            }

            return [$total, 0, 0, '0.00'];
        }

        $configuracion = ConfiguracionNegocio::actual();
        $registrarPuntos = new RegistrarMovimientoPuntosAction;

        $descuentoPorPuntos = '0.00';
        $puntosUtilizados = 0;

        if ($puntosUtilizadosSolicitados > 0) {
            $descuentoPorPuntos = bcmul((string) $puntosUtilizadosSolicitados, (string) $configuracion->valor_por_punto, 2);

            if (bccomp($descuentoPorPuntos, $total, 2) === 1) {
                throw new InvalidArgumentException('El descuento por puntos no puede superar el total de la venta.');
            }

            $registrarPuntos->handle($venta->cliente_id, TipoMovimientoPuntos::Canjeado, $puntosUtilizadosSolicitados, $venta->id, $usuarioId);

            $puntosUtilizados = $puntosUtilizadosSolicitados;
        }

        $totalFinal = bcsub($total, $descuentoPorPuntos, 2);

        $puntosGanados = $configuracion->soles_por_punto > 0
            ? (int) bcdiv($totalFinal, (string) $configuracion->soles_por_punto, 0)
            : 0;

        if ($puntosGanados > 0) {
            $registrarPuntos->handle($venta->cliente_id, TipoMovimientoPuntos::Ganado, $puntosGanados, $venta->id, $usuarioId);
        }

        return [$totalFinal, $puntosGanados, $puntosUtilizados, $descuentoPorPuntos];
    }
}
