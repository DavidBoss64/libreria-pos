<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Actions\Ventas\CerrarVentaAction;
use App\Actions\Ventas\CrearPreventaAction;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TipoMovimientoPuntos;
use App\Enums\UserRole;
use App\Enums\VentaEstado;
use App\Enums\VentaMetodoPago;
use App\Exceptions\PuntosInsuficientesException;
use App\Exceptions\VentaSinStockDisponibleException;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoPuntos;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class CerrarVentaActionTest extends TestCase
{
    use RefreshDatabase;

    private function crearSucursal(string $nombre): Sucursal
    {
        return Sucursal::create(['nombre' => "Sucursal {$nombre}", 'estado' => true]);
    }

    private function crearVariante(string $codigo, float $precioUnidad = 6.50): ProductoVariante
    {
        $categoria = Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos-'.uniqid()]);

        $producto = Producto::create([
            'nombre' => 'Cuaderno 100 hojas',
            'slug' => 'cuaderno-100-hojas-'.uniqid(),
            'categoria_id' => $categoria->id,
            'estado' => true,
        ]);

        return ProductoVariante::create([
            'producto_id' => $producto->id,
            'codigo_interno' => $codigo,
            'costo_real' => 5.00,
            'precio_venta_unidad' => $precioUnidad,
            'precio_venta_docena' => $precioUnidad - 0.50,
            'precio_venta_mayor' => $precioUnidad - 1.00,
            'estado' => true,
        ]);
    }

    private function darStockReal(Sucursal $sucursal, ProductoVariante $variante, int $cantidad, User $almacenero): void
    {
        if ($cantidad === 0) {
            return;
        }

        (new RegistrarMovimientoInventarioAction)->handle(
            $sucursal->almacenTienda()->id,
            $variante->id,
            TipoMovimientoInventario::Ingreso,
            $cantidad,
            'Stock inicial de prueba',
            $almacenero->id,
        );
    }

    private function crearCliente(int $puntosIniciales = 0): Cliente
    {
        return Cliente::create([
            'nombres' => 'María',
            'apellidos' => 'Gómez',
            'puntos_acumulados' => $puntosIniciales,
        ]);
    }

    public function test_cierra_venta_descuenta_stock_libera_comprometido_y_completa(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante('CUA-001');
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        $this->darStockReal($sucursal, $variante, 10, $almacenero);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Juan Polera Roja',
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 3],
            ],
        ]);

        $resultado = (new CerrarVentaAction)->handle($venta, [
            'usuario_id' => $cajero->id,
            'metodo_pago' => VentaMetodoPago::Efectivo,
        ]);

        $this->assertSame(VentaEstado::Completado, $resultado->venta->estado);
        $this->assertSame($cajero->id, $resultado->venta->usuario_id);
        $this->assertSame([], $resultado->itemsRechazados);
        $this->assertSame('19.50', (string) $resultado->venta->total);

        $almacenTienda = $sucursal->almacenTienda();
        $inventario = Inventario::where('almacen_id', $almacenTienda->id)->where('producto_variante_id', $variante->id)->first();

        $this->assertSame(7, $inventario->cantidad, 'Debe descontar el stock real (10 - 3).');
        $this->assertSame(0, $inventario->cantidad_comprometida, 'El cierre debe liberar lo comprometido.');
    }

    public function test_pago_digital_requiere_referencia_pago(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante('CUA-001');
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        $this->darStockReal($sucursal, $variante, 10, $almacenero);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Juan',
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 1],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CerrarVentaAction)->handle($venta, [
            'usuario_id' => $cajero->id,
            'metodo_pago' => VentaMetodoPago::Qr,
        ]);
    }

    /**
     * Invariante de negocio (suite normal, SQLite) exigida por CLAUDE.md regla 10:
     * una línea que excede el stock disponible se rechaza sin tumbar el resto de la
     * venta, y el stock nunca queda negativo. La prueba de bloqueo de fila real bajo
     * concurrencia ya vive en InventarioConcurrenciaTest (Fase 3) — CerrarVentaAction
     * reutiliza ese mismo primitivo (RegistrarMovimientoInventarioAction), no se
     * duplica un arnés de dos procesos aparte para este Action (ver nota en
     * PLAN_DESARROLLO.md Paso 4.2).
     */
    public function test_item_sin_stock_suficiente_se_rechaza_pero_el_resto_de_la_venta_se_completa(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $varianteA = $this->crearVariante('CUA-001', 6.50);
        $varianteB = $this->crearVariante('CUA-002', 3.00);
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        $this->darStockReal($sucursal, $varianteA, 5, $almacenero);
        // Sin stock real de $varianteB (0) — simula una venta concurrente que se la llevó.

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Juan',
            'detalles' => [
                ['producto_variante_id' => $varianteA->id, 'cantidad' => 2],
                ['producto_variante_id' => $varianteB->id, 'cantidad' => 3],
            ],
        ]);

        $resultado = (new CerrarVentaAction)->handle($venta, [
            'usuario_id' => $cajero->id,
            'metodo_pago' => VentaMetodoPago::Efectivo,
        ]);

        $this->assertSame(VentaEstado::Completado, $resultado->venta->estado);
        $this->assertCount(1, $resultado->itemsRechazados);
        $this->assertSame($varianteB->id, $resultado->itemsRechazados[0]->productoVarianteId);
        $this->assertCount(1, $resultado->venta->detalles, 'Solo debe quedar la línea que sí se vendió.');
        $this->assertSame('13.00', (string) $resultado->venta->total, 'Total debe reflejar solo la línea A (2 x 6.50).');

        $almacenTienda = $sucursal->almacenTienda();

        $inventarioA = Inventario::where('almacen_id', $almacenTienda->id)->where('producto_variante_id', $varianteA->id)->first();
        $this->assertSame(3, $inventarioA->cantidad, 'Stock A: 5 - 2 vendidas.');
        $this->assertSame(0, $inventarioA->cantidad_comprometida);

        $inventarioB = Inventario::where('almacen_id', $almacenTienda->id)->where('producto_variante_id', $varianteB->id)->first();
        $this->assertSame(0, $inventarioB->cantidad, 'Stock B nunca debe quedar negativo.');
        $this->assertSame(0, $inventarioB->cantidad_comprometida, 'Debe liberarse aunque el ítem se haya rechazado.');
    }

    public function test_venta_sin_ningun_item_disponible_lanza_excepcion_y_no_completa_nada(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante('CUA-001');
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);

        // Sin stock real en absoluto.
        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Juan',
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 1],
            ],
        ]);

        $this->expectException(VentaSinStockDisponibleException::class);

        try {
            (new CerrarVentaAction)->handle($venta, [
                'usuario_id' => $cajero->id,
                'metodo_pago' => VentaMetodoPago::Efectivo,
            ]);
        } finally {
            $venta->refresh();
            $this->assertSame(VentaEstado::Pendiente, $venta->estado, 'No debe completarse ninguna venta sin al menos un ítem vendible.');
            $this->assertCount(1, $venta->detalles, 'El detalle original no debe perderse si todo se revierte.');
        }
    }

    public function test_no_permite_cerrar_una_venta_que_no_esta_pendiente(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);

        $venta = Venta::create([
            'sucursal_id' => $sucursal->id,
            'cliente_temporal' => 'Juan',
            'total' => 10,
            'estado' => VentaEstado::Anulado,
        ]);

        $this->expectException(RuntimeException::class);

        (new CerrarVentaAction)->handle($venta, [
            'usuario_id' => $cajero->id,
            'metodo_pago' => VentaMetodoPago::Efectivo,
        ]);
    }

    /**
     * Fidelización (LOGICA_NEGOCIO.md sección 9, Paso 4.5): 1 punto por cada
     * `soles_por_punto` (default 30) del total FINAL de la venta. Total = 7 x 10.00 = 70.00.
     */
    public function test_venta_con_cliente_registrado_gana_puntos_segun_el_total_final(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante('CUA-001', 10.00);
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $cliente = $this->crearCliente();

        $this->darStockReal($sucursal, $variante, 10, $almacenero);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_id' => $cliente->id,
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 7],
            ],
        ]);

        $resultado = (new CerrarVentaAction)->handle($venta, [
            'usuario_id' => $cajero->id,
            'metodo_pago' => VentaMetodoPago::Efectivo,
        ]);

        $this->assertSame('70.00', (string) $resultado->venta->total);
        $this->assertSame(2, $resultado->venta->puntos_ganados);
        $this->assertSame(0, $resultado->venta->puntos_utilizados);
        $this->assertSame(2, $cliente->fresh()->puntos_acumulados);
        $this->assertDatabaseHas('movimientos_puntos', [
            'cliente_id' => $cliente->id,
            'venta_id' => $venta->id,
            'tipo' => TipoMovimientoPuntos::Ganado->value,
            'puntos' => 2,
            'saldo_despues' => 2,
        ]);
    }

    public function test_venta_con_cliente_temporal_no_acumula_puntos(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante('CUA-001', 10.00);
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        $this->darStockReal($sucursal, $variante, 10, $almacenero);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Juan',
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 7],
            ],
        ]);

        $resultado = (new CerrarVentaAction)->handle($venta, [
            'usuario_id' => $cajero->id,
            'metodo_pago' => VentaMetodoPago::Efectivo,
        ]);

        $this->assertSame(0, $resultado->venta->puntos_ganados);
        $this->assertSame(0, MovimientoPuntos::count());
    }

    /**
     * El Cajero (nunca el Vendedor) ofrece el canje al cerrar la venta — decisión
     * confirmada explícitamente en la sesión de Paso 4.5. Total bruto = 5 x 10.00 = 50.00;
     * canjear 10 puntos (valor_por_punto default 0.30) descuenta 3.00 → total final 47.00;
     * puntos ganados sobre el total final = floor(47.00 / 30) = 1.
     */
    public function test_cajero_puede_canjear_puntos_del_cliente_y_se_descuenta_del_total(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante('CUA-001', 10.00);
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $cliente = $this->crearCliente(10);

        $this->darStockReal($sucursal, $variante, 10, $almacenero);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_id' => $cliente->id,
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 5],
            ],
        ]);

        $resultado = (new CerrarVentaAction)->handle($venta, [
            'usuario_id' => $cajero->id,
            'metodo_pago' => VentaMetodoPago::Efectivo,
            'puntos_utilizados' => 10,
        ]);

        $this->assertSame('47.00', (string) $resultado->venta->total);
        $this->assertSame('3.00', (string) $resultado->venta->descuento_por_puntos);
        $this->assertSame(10, $resultado->venta->puntos_utilizados);
        $this->assertSame(1, $resultado->venta->puntos_ganados);
        $this->assertSame(1, $cliente->fresh()->puntos_acumulados, 'Saldo final: 10 - 10 canjeados + 1 ganado.');
        $this->assertDatabaseHas('movimientos_puntos', [
            'cliente_id' => $cliente->id,
            'venta_id' => $venta->id,
            'tipo' => TipoMovimientoPuntos::Canjeado->value,
            'puntos' => -10,
        ]);
    }

    public function test_canje_que_supera_el_total_de_la_venta_se_rechaza_y_no_completa_nada(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante('CUA-001', 10.00);
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $cliente = $this->crearCliente(1000);

        $this->darStockReal($sucursal, $variante, 10, $almacenero);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_id' => $cliente->id,
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 1],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        try {
            (new CerrarVentaAction)->handle($venta, [
                'usuario_id' => $cajero->id,
                'metodo_pago' => VentaMetodoPago::Efectivo,
                'puntos_utilizados' => 1000,
            ]);
        } finally {
            $venta->refresh();
            $this->assertSame(VentaEstado::Pendiente, $venta->estado);
            $this->assertSame(1000, $cliente->fresh()->puntos_acumulados, 'El canje rechazado no debe tocar el saldo del cliente.');

            $almacenTienda = $sucursal->almacenTienda();
            $inventario = Inventario::where('almacen_id', $almacenTienda->id)->where('producto_variante_id', $variante->id)->first();
            $this->assertSame(10, $inventario->cantidad, 'El rollback debe revertir también el descuento de stock.');
        }
    }

    public function test_canje_que_excede_el_saldo_del_cliente_lanza_excepcion_y_revierte_todo(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante('CUA-001', 10.00);
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $cliente = $this->crearCliente(2);

        $this->darStockReal($sucursal, $variante, 10, $almacenero);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_id' => $cliente->id,
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 5],
            ],
        ]);

        $this->expectException(PuntosInsuficientesException::class);

        try {
            (new CerrarVentaAction)->handle($venta, [
                'usuario_id' => $cajero->id,
                'metodo_pago' => VentaMetodoPago::Efectivo,
                'puntos_utilizados' => 5,
            ]);
        } finally {
            $venta->refresh();
            $this->assertSame(VentaEstado::Pendiente, $venta->estado);
            $this->assertSame(2, $cliente->fresh()->puntos_acumulados);
        }
    }

    public function test_solo_un_cliente_registrado_puede_canjear_puntos(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante('CUA-001', 10.00);
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'sucursal_id' => $sucursal->id, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        $this->darStockReal($sucursal, $variante, 10, $almacenero);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Juan',
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 1],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CerrarVentaAction)->handle($venta, [
            'usuario_id' => $cajero->id,
            'metodo_pago' => VentaMetodoPago::Efectivo,
            'puntos_utilizados' => 5,
        ]);
    }
}
