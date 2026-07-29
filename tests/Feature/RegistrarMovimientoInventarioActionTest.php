<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Enums\TipoMovimientoInventario;
use App\Enums\UserRole;
use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RegistrarMovimientoInventarioActionTest extends TestCase
{
    use RefreshDatabase;

    private function crearAlmacen(): Almacen
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal Central', 'estado' => true]);

        return Almacen::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Depósito Central',
            'tipo' => 'deposito',
            'estado' => true,
        ]);
    }

    private function crearVariante(): ProductoVariante
    {
        $categoria = Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos']);

        $producto = Producto::create([
            'nombre' => 'Cuaderno 100 hojas',
            'slug' => 'cuaderno-100-hojas',
            'categoria_id' => $categoria->id,
            'estado' => true,
        ]);

        return ProductoVariante::create([
            'producto_id' => $producto->id,
            'codigo_interno' => 'CUA-001',
            'costo_real' => 5.00,
            'precio_venta_unidad' => 6.50,
            'precio_venta_docena' => 6.00,
            'precio_venta_mayor' => 5.50,
            'estado' => true,
        ]);
    }

    public function test_ingreso_crea_la_fila_de_inventario_y_suma_stock(): void
    {
        $almacen = $this->crearAlmacen();
        $variante = $this->crearVariante();
        $usuario = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $movimiento = (new RegistrarMovimientoInventarioAction())->handle(
            almacenId: $almacen->id,
            productoVarianteId: $variante->id,
            tipoMovimiento: TipoMovimientoInventario::Ingreso,
            cantidad: 20,
            motivo: 'Compra inicial',
            usuarioId: $usuario->id,
        );

        $this->assertSame(20, $movimiento->cantidad);
        $this->assertSame(20, $movimiento->saldo_despues);

        $inventario = Inventario::where('almacen_id', $almacen->id)
            ->where('producto_variante_id', $variante->id)
            ->first();

        $this->assertSame(20, $inventario->cantidad);
    }

    public function test_salida_resta_stock_y_registra_kardex_con_cantidad_negativa(): void
    {
        $almacen = $this->crearAlmacen();
        $variante = $this->crearVariante();
        $usuario = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $accion = new RegistrarMovimientoInventarioAction();

        $accion->handle($almacen->id, $variante->id, TipoMovimientoInventario::Ingreso, 20, 'Compra inicial', $usuario->id);
        $movimiento = $accion->handle($almacen->id, $variante->id, TipoMovimientoInventario::Salida, 8, 'Venta', $usuario->id);

        $this->assertSame(-8, $movimiento->cantidad);
        $this->assertSame(12, $movimiento->saldo_despues);
    }

    public function test_salida_con_stock_insuficiente_lanza_excepcion_y_no_modifica_inventario(): void
    {
        $almacen = $this->crearAlmacen();
        $variante = $this->crearVariante();
        $usuario = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $accion = new RegistrarMovimientoInventarioAction();

        $accion->handle($almacen->id, $variante->id, TipoMovimientoInventario::Ingreso, 5, 'Compra inicial', $usuario->id);

        $this->expectException(StockInsuficienteException::class);

        try {
            $accion->handle($almacen->id, $variante->id, TipoMovimientoInventario::Salida, 10, 'Venta', $usuario->id);
        } finally {
            $inventario = Inventario::where('almacen_id', $almacen->id)
                ->where('producto_variante_id', $variante->id)
                ->first();

            $this->assertSame(5, $inventario->cantidad, 'El stock no debe cambiar cuando la salida se rechaza.');
            $this->assertSame(
                1,
                $variante->movimientosInventario()->count(),
                'Solo debe existir el movimiento de ingreso inicial; la salida rechazada no debe dejar rastro en el Kardex.'
            );
        }
    }

    public function test_dos_salidas_secuenciales_que_exceden_el_stock_disponible_solo_permiten_la_primera(): void
    {
        // No prueba lockForUpdate() real (eso requiere Postgres real, ver
        // tests/Feature/Concurrency/InventarioConcurrenciaTest.php @group concurrency).
        // Verifica la invariante de negocio: el stock nunca queda negativo.
        $almacen = $this->crearAlmacen();
        $variante = $this->crearVariante();
        $usuario = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $accion = new RegistrarMovimientoInventarioAction();

        $accion->handle($almacen->id, $variante->id, TipoMovimientoInventario::Ingreso, 10, 'Compra inicial', $usuario->id);

        $accion->handle($almacen->id, $variante->id, TipoMovimientoInventario::Salida, 7, 'Venta 1', $usuario->id);

        $this->expectException(StockInsuficienteException::class);
        $accion->handle($almacen->id, $variante->id, TipoMovimientoInventario::Salida, 7, 'Venta 2', $usuario->id);
    }

    public function test_ajuste_admite_delta_negativo_por_merma(): void
    {
        $almacen = $this->crearAlmacen();
        $variante = $this->crearVariante();
        $usuario = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $accion = new RegistrarMovimientoInventarioAction();

        $accion->handle($almacen->id, $variante->id, TipoMovimientoInventario::Ingreso, 10, 'Compra inicial', $usuario->id);
        $movimiento = $accion->handle($almacen->id, $variante->id, TipoMovimientoInventario::Ajuste, -2, 'Merma por daño', $usuario->id);

        $this->assertSame(-2, $movimiento->cantidad);
        $this->assertSame(8, $movimiento->saldo_despues);
    }

    public function test_cantidad_cero_o_negativa_en_ingreso_o_salida_lanza_excepcion(): void
    {
        $almacen = $this->crearAlmacen();
        $variante = $this->crearVariante();
        $usuario = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->expectException(InvalidArgumentException::class);

        (new RegistrarMovimientoInventarioAction())->handle(
            $almacen->id,
            $variante->id,
            TipoMovimientoInventario::Ingreso,
            0,
            'Inválido',
            $usuario->id,
        );
    }

    public function test_ajuste_con_cantidad_cero_lanza_excepcion(): void
    {
        $almacen = $this->crearAlmacen();
        $variante = $this->crearVariante();
        $usuario = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->expectException(InvalidArgumentException::class);

        (new RegistrarMovimientoInventarioAction())->handle(
            $almacen->id,
            $variante->id,
            TipoMovimientoInventario::Ajuste,
            0,
            'Inválido',
            $usuario->id,
        );
    }
}
