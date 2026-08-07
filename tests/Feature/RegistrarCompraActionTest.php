<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Compras\AnularCompraAction;
use App\Actions\Compras\RegistrarCompraAction;
use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Enums\CompraEstado;
use App\Enums\TipoMovimientoInventario;
use App\Enums\UserRole;
use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class RegistrarCompraActionTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;

    private ProductoVariante $variante;

    private Proveedor $proveedor;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Sucursal Central', 'estado' => true]);
        $this->almacen = Almacen::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Depósito Central',
            'tipo' => 'deposito',
            'estado' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos']);
        $producto = Producto::create([
            'nombre' => 'Cuaderno 100 hojas',
            'slug' => 'cuaderno-100-hojas',
            'categoria_id' => $categoria->id,
            'estado' => true,
        ]);
        $this->variante = ProductoVariante::create([
            'producto_id' => $producto->id,
            'codigo_interno' => 'CUA-001',
            'costo_real' => 5.00,
            'precio_venta_unidad' => 6.50,
            'precio_venta_docena' => 6.00,
            'precio_venta_mayor' => 5.50,
            'estado' => true,
        ]);

        $this->proveedor = Proveedor::create(['razon_social' => 'Distribuidora Test']);
        $this->usuario = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    }

    public function test_registrar_compra_por_unidad_inyecta_stock_y_calcula_el_total(): void
    {
        $compra = (new RegistrarCompraAction)->handle(
            proveedorId: $this->proveedor->id,
            almacenId: $this->almacen->id,
            usuarioId: $this->usuario->id,
            numeroFactura: 'F001-000123',
            lineas: [
                ['producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'unidad', 'cantidad' => 10, 'precio_compra_unitario' => '1.50'],
            ],
        );

        $this->assertSame(CompraEstado::Completado, $compra->estado);
        $this->assertSame('15.00', (string) $compra->total);
        $this->assertSame('F001-000123', $compra->numero_factura);

        $inventario = Inventario::where('almacen_id', $this->almacen->id)
            ->where('producto_variante_id', $this->variante->id)
            ->first();

        $this->assertSame(10, $inventario->cantidad);

        $this->assertDatabaseHas('movimientos_inventario', [
            'almacen_id' => $this->almacen->id,
            'producto_variante_id' => $this->variante->id,
            'tipo_movimiento' => 'ingreso',
            'cantidad' => 10,
            'referencia_tipo' => Compra::class,
            'referencia_id' => $compra->id,
        ]);
    }

    public function test_registrar_compra_por_caja_calcula_unidades_y_actualiza_el_tamano_de_caja_del_producto(): void
    {
        $compra = (new RegistrarCompraAction)->handle(
            proveedorId: $this->proveedor->id,
            almacenId: $this->almacen->id,
            usuarioId: $this->usuario->id,
            numeroFactura: null,
            lineas: [
                ['producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'caja', 'unidades_por_caja' => 24, 'cantidad_cajas' => 3, 'precio_compra_unitario' => '1.20'],
            ],
        );

        $this->assertSame('86.40', (string) $compra->total, '3 cajas × 24 = 72 unidades × 1.20 = 86.40.');

        $inventario = Inventario::where('almacen_id', $this->almacen->id)
            ->where('producto_variante_id', $this->variante->id)
            ->first();

        $this->assertSame(72, $inventario->cantidad);
        $this->assertSame(24, $this->variante->fresh()->unidades_por_caja);
    }

    public function test_registrar_compra_con_varias_lineas_suma_el_total_correctamente(): void
    {
        $lapiz = ProductoVariante::create([
            'producto_id' => $this->variante->producto_id,
            'codigo_interno' => 'LAP-001',
            'costo_real' => 0.50,
            'precio_venta_unidad' => 1.00,
            'precio_venta_docena' => 0.90,
            'precio_venta_mayor' => 0.80,
            'estado' => true,
        ]);

        $compra = (new RegistrarCompraAction)->handle(
            proveedorId: $this->proveedor->id,
            almacenId: $this->almacen->id,
            usuarioId: $this->usuario->id,
            numeroFactura: null,
            lineas: [
                ['producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'unidad', 'cantidad' => 5, 'precio_compra_unitario' => '1.50'],
                ['producto_variante_id' => $lapiz->id, 'modo_cantidad' => 'unidad', 'cantidad' => 20, 'precio_compra_unitario' => '0.40'],
            ],
        );

        $this->assertSame('15.50', (string) $compra->total, '(5 × 1.50 = 7.50) + (20 × 0.40 = 8.00) = 15.50.');
        $this->assertCount(2, $compra->detalles);
    }

    public function test_no_se_puede_registrar_una_compra_sin_lineas(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RegistrarCompraAction)->handle(
            proveedorId: $this->proveedor->id,
            almacenId: $this->almacen->id,
            usuarioId: $this->usuario->id,
            numeroFactura: null,
            lineas: [],
        );
    }

    public function test_anular_una_compra_revierte_el_stock_y_marca_el_estado(): void
    {
        $compra = (new RegistrarCompraAction)->handle(
            proveedorId: $this->proveedor->id,
            almacenId: $this->almacen->id,
            usuarioId: $this->usuario->id,
            numeroFactura: null,
            lineas: [
                ['producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'unidad', 'cantidad' => 10, 'precio_compra_unitario' => '1.50'],
            ],
        );

        $anulada = (new AnularCompraAction)->handle($compra, $this->usuario->id);

        $this->assertSame(CompraEstado::Anulado, $anulada->estado);

        $inventario = Inventario::where('almacen_id', $this->almacen->id)
            ->where('producto_variante_id', $this->variante->id)
            ->first();

        $this->assertSame(0, $inventario->cantidad);
    }

    public function test_anular_una_compra_ya_anulada_lanza_excepcion(): void
    {
        $compra = (new RegistrarCompraAction)->handle(
            proveedorId: $this->proveedor->id,
            almacenId: $this->almacen->id,
            usuarioId: $this->usuario->id,
            numeroFactura: null,
            lineas: [
                ['producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'unidad', 'cantidad' => 10, 'precio_compra_unitario' => '1.50'],
            ],
        );

        (new AnularCompraAction)->handle($compra, $this->usuario->id);

        $this->expectException(RuntimeException::class);
        (new AnularCompraAction)->handle($compra->fresh(), $this->usuario->id);
    }

    public function test_anular_una_compra_cuyo_stock_ya_se_vendio_se_rechaza_sin_dejar_nada_a_medias(): void
    {
        $compra = (new RegistrarCompraAction)->handle(
            proveedorId: $this->proveedor->id,
            almacenId: $this->almacen->id,
            usuarioId: $this->usuario->id,
            numeroFactura: null,
            lineas: [
                ['producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'unidad', 'cantidad' => 10, 'precio_compra_unitario' => '1.50'],
            ],
        );

        // Se vende/traspasa la mitad del stock que trajo la compra, dejando solo 5 disponibles.
        app(RegistrarMovimientoInventarioAction::class)->handle(
            almacenId: $this->almacen->id,
            productoVarianteId: $this->variante->id,
            tipoMovimiento: TipoMovimientoInventario::Salida,
            cantidad: 5,
            motivo: 'Venta de prueba',
            usuarioId: $this->usuario->id,
        );

        $this->expectException(StockInsuficienteException::class);
        (new AnularCompraAction)->handle($compra->fresh(), $this->usuario->id);

        $inventario = Inventario::where('almacen_id', $this->almacen->id)
            ->where('producto_variante_id', $this->variante->id)
            ->first();

        $this->assertSame(5, $inventario->cantidad, 'El stock no debe cambiar cuando la anulación se rechaza.');
        $this->assertSame(CompraEstado::Completado, $compra->fresh()->estado, 'La compra debe seguir completado, no anulado a medias.');
    }
}
