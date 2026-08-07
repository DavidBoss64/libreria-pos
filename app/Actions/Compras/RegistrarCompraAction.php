<?php

declare(strict_types=1);

namespace App\Actions\Compras;

use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Enums\CompraEstado;
use App\Enums\TipoMovimientoInventario;
use App\Filament\Support\CamposCantidadPorCaja;
use App\Models\Compra;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrarCompraAction
{
    /**
     * Flujo de un solo paso (decisión confirmada con el propietario, sesión 2026-08-05):
     * el Admin o el Almacenero registran lo que físicamente llegó del proveedor y el
     * stock entra de inmediato — no existe un "pedido" previo dentro del sistema. Un
     * flujo de dos etapas (pedido → recepción parcial) queda anotado como mejora futura.
     *
     * Cada línea puede venir en modo "por caja" (ver `CamposCantidadPorCaja`, mismo
     * componente que usa el Ajuste Manual) — la resolución a unidades y la eventual
     * actualización del tamaño de caja del producto ocurren aquí, dentro de la misma
     * transacción que el resto de la compra (nunca a medias).
     *
     * @param  array<int, array{producto_variante_id: int, modo_cantidad?: string, cantidad?: int, unidades_por_caja?: int, cantidad_cajas?: int, precio_compra_unitario: numeric-string}>  $lineas
     */
    public function handle(
        int $proveedorId,
        int $almacenId,
        int $usuarioId,
        ?string $numeroFactura,
        array $lineas,
    ): Compra {
        if ($lineas === []) {
            throw new InvalidArgumentException('Una compra debe registrarse con al menos un producto.');
        }

        return DB::transaction(function () use ($proveedorId, $almacenId, $usuarioId, $numeroFactura, $lineas) {
            $compra = Compra::create([
                'proveedor_id' => $proveedorId,
                'almacen_id' => $almacenId,
                'usuario_id' => $usuarioId,
                'numero_factura' => $numeroFactura,
                'total' => 0,
                'estado' => CompraEstado::Completado,
            ]);

            $total = '0.00';

            foreach ($lineas as $linea) {
                CamposCantidadPorCaja::actualizarTamanoCajaSiCambio($linea);
                $cantidad = CamposCantidadPorCaja::cantidadFinalEnUnidades($linea);

                $precioUnitario = (string) $linea['precio_compra_unitario'];
                $subtotal = bcmul((string) $cantidad, $precioUnitario, 2);
                $total = bcadd($total, $subtotal, 2);

                $compra->detalles()->create([
                    'producto_variante_id' => $linea['producto_variante_id'],
                    'cantidad' => $cantidad,
                    'precio_compra_unitario' => $precioUnitario,
                    'subtotal' => $subtotal,
                ]);

                app(RegistrarMovimientoInventarioAction::class)->handle(
                    almacenId: $almacenId,
                    productoVarianteId: (int) $linea['producto_variante_id'],
                    tipoMovimiento: TipoMovimientoInventario::Ingreso,
                    cantidad: $cantidad,
                    motivo: "Compra #{$compra->id}".($numeroFactura ? " — Factura {$numeroFactura}" : ''),
                    usuarioId: $usuarioId,
                    referenciaTipo: Compra::class,
                    referenciaId: $compra->id,
                );
            }

            $compra->update(['total' => $total]);

            return $compra->fresh('detalles');
        });
    }
}
