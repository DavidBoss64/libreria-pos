<?php

declare(strict_types=1);

namespace App\Actions\Ventas;

use App\Actions\Inventario\AjustarComprometidoAction;
use App\Enums\VentaEstado;
use App\Enums\VentaOrigen;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\PrecioService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CrearPreventaAction
{
    /**
     * Fase A del flujo de Pre-venta (ver LOGICA_NEGOCIO.md sección 3): el Vendedor
     * arma la canasta. No mueve stock real — solo incrementa `cantidad_comprometida`
     * (indicador no bloqueante, ver AjustarComprometidoAction) en el almacén 'tienda'
     * de la propia sucursal. El descuento real de stock ocurre recién en el cierre
     * (CerrarVentaAction), con lockForUpdate().
     *
     * @param  array{sucursal_id: int, vendedor_id: int, cliente_id?: ?int, cliente_temporal?: ?string, detalles: list<array{producto_variante_id: int, cantidad: int, forzar_mayorista?: bool}>}  $datos
     */
    public function handle(array $datos): Venta
    {
        if (($datos['detalles'] ?? []) === []) {
            throw new InvalidArgumentException('Una pre-venta debe armarse con al menos un producto.');
        }

        $clienteId = $datos['cliente_id'] ?? null;
        $clienteTemporal = $datos['cliente_temporal'] ?? null;

        if ($clienteId === null && ($clienteTemporal === null || trim($clienteTemporal) === '')) {
            throw new InvalidArgumentException(
                'Una pre-venta necesita un cliente registrado o un nombre temporal para la cola de caja.'
            );
        }

        $sucursal = Sucursal::findOrFail($datos['sucursal_id']);
        $almacenTienda = $sucursal->almacenTienda();

        if ($almacenTienda === null) {
            throw new RuntimeException("La sucursal {$sucursal->nombre} no tiene un almacén tipo 'tienda' configurado.");
        }

        return DB::transaction(function () use ($datos, $sucursal, $almacenTienda, $clienteId, $clienteTemporal) {
            $precioService = new PrecioService();
            $ajustarComprometido = new AjustarComprometidoAction();

            $venta = Venta::create([
                'sucursal_id' => $sucursal->id,
                'vendedor_id' => $datos['vendedor_id'],
                'cliente_id' => $clienteId,
                'cliente_temporal' => $clienteTemporal,
                'total' => 0,
                'origen' => VentaOrigen::Pos,
                'estado' => VentaEstado::Pendiente,
                'expira_en' => now()->addMinutes((int) config('ventas.expira_preventa_minutos')),
            ]);

            // Derivado del `id` autoincremental, nunca de un contador consultado y
            // sumado en la aplicación (condición de carrera) — ver CLAUDE.md regla 11.
            $venta->numero_ticket = 'V-'.str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT);
            $venta->save();

            $total = '0.00';

            foreach ($datos['detalles'] as $linea) {
                $calculado = $precioService->calcularPrecio(
                    $linea['producto_variante_id'],
                    $linea['cantidad'],
                    $linea['forzar_mayorista'] ?? false,
                );

                $venta->detalles()->create([
                    'producto_variante_id' => $linea['producto_variante_id'],
                    'cantidad' => $linea['cantidad'],
                    'tipo_precio_aplicado' => $calculado->tipo,
                    'precio_unitario' => $calculado->precioUnitario,
                    'subtotal' => $calculado->subtotal,
                ]);

                $total = bcadd($total, $calculado->subtotal, 2);

                $ajustarComprometido->handle($almacenTienda->id, $linea['producto_variante_id'], $linea['cantidad']);
            }

            $venta->update(['total' => $total]);

            return $venta->fresh(['detalles']);
        });
    }
}
