<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Almacens\Pages\VerStockAlmacen;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VerStockAlmacenPageTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacenA;

    private Almacen $almacenB;

    private ProductoVariante $varianteA;

    private ProductoVariante $varianteB;

    private Inventario $inventarioA;

    private Inventario $inventarioB;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Sucursal Central', 'estado' => true]);

        $this->almacenA = Almacen::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Depósito A',
            'tipo' => 'deposito',
            'estado' => true,
        ]);

        $this->almacenB = Almacen::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Depósito B',
            'tipo' => 'deposito',
            'estado' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos']);

        $productoA = Producto::create([
            'nombre' => 'Cuaderno 100 hojas',
            'slug' => 'cuaderno-100-hojas',
            'categoria_id' => $categoria->id,
            'estado' => true,
        ]);
        $this->varianteA = ProductoVariante::create([
            'producto_id' => $productoA->id,
            'codigo_interno' => 'CUA-001',
            'costo_real' => 5.00,
            'precio_venta_unidad' => 6.50,
            'precio_venta_docena' => 6.00,
            'precio_venta_mayor' => 5.50,
            'estado' => true,
        ]);

        $productoB = Producto::create([
            'nombre' => 'Lápiz 2B',
            'slug' => 'lapiz-2b',
            'categoria_id' => $categoria->id,
            'estado' => true,
        ]);
        $this->varianteB = ProductoVariante::create([
            'producto_id' => $productoB->id,
            'codigo_interno' => 'LAP-002',
            'costo_real' => 1.00,
            'precio_venta_unidad' => 1.50,
            'precio_venta_docena' => 1.40,
            'precio_venta_mayor' => 1.30,
            'estado' => true,
        ]);

        $this->inventarioA = Inventario::create([
            'almacen_id' => $this->almacenA->id,
            'producto_variante_id' => $this->varianteA->id,
            'cantidad' => 20,
        ]);

        $this->inventarioB = Inventario::create([
            'almacen_id' => $this->almacenB->id,
            'producto_variante_id' => $this->varianteB->id,
            'cantidad' => 30,
        ]);
    }

    public function test_un_rol_no_admin_no_puede_acceder_a_la_vista_de_stock_del_almacen(): void
    {
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);

        $this->actingAs($vendedor)
            ->get(VerStockAlmacen::getUrl(['record' => $this->almacenA]))
            ->assertForbidden();
    }

    public function test_el_admin_puede_acceder_a_la_vista_de_stock_del_almacen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(VerStockAlmacen::getUrl(['record' => $this->almacenA]))
            ->assertSuccessful();
    }

    public function test_la_tabla_solo_muestra_el_inventario_del_almacen_seleccionado(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(VerStockAlmacen::class, ['record' => $this->almacenA->getRouteKey()])
            ->assertCanSeeTableRecords([$this->inventarioA])
            ->assertCanNotSeeTableRecords([$this->inventarioB]);
    }

    public function test_registrar_ajuste_desde_la_vista_de_stock_usa_el_almacen_ya_fijado(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(VerStockAlmacen::class, ['record' => $this->almacenA->getRouteKey()])
            ->callAction('registrarAjuste', data: [
                'producto_id' => $this->varianteA->producto_id,
                'producto_variante_id' => $this->varianteA->id,
                'cantidad' => 5,
                'motivo' => 'Corrección de conteo físico',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('movimientos_inventario', [
            'almacen_id' => $this->almacenA->id,
            'producto_variante_id' => $this->varianteA->id,
            'tipo_movimiento' => 'ajuste',
            'cantidad' => 5,
            'saldo_despues' => 25,
            'motivo' => 'Corrección de conteo físico',
            'usuario_id' => $admin->id,
        ]);

        $this->assertSame(0, MovimientoInventario::where('almacen_id', $this->almacenB->id)->count());

        $this->inventarioA->refresh();
        $this->assertSame(25, $this->inventarioA->cantidad);
    }
}
