<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CompraEstado;
use App\Enums\UserRole;
use App\Filament\Almacen\Resources\Compras\Pages\CreateCompra as AlmacenCreateCompra;
use App\Filament\Resources\Compras\Pages\CreateCompra as AdminCreateCompra;
use App\Filament\Resources\Compras\Pages\ListCompras;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompraResourceTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Almacen $almacenA;

    private Almacen $almacenB;

    private ProductoVariante $variante;

    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sucursal Central', 'estado' => true]);
        $this->almacenA = Almacen::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Depósito A',
            'tipo' => 'deposito',
            'estado' => true,
        ]);
        $this->almacenB = Almacen::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Depósito B',
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
    }

    public function test_admin_puede_acceder_a_compras(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/admin/compras')
            ->assertSuccessful();
    }

    public function test_almacenero_puede_acceder_a_compras(): void
    {
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almacenero->almacenes()->attach($this->almacenA->id);

        $this->actingAs($almacenero)
            ->get('/almacen/compras')
            ->assertSuccessful();
    }

    public function test_admin_puede_registrar_una_compra_para_cualquier_almacen(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(AdminCreateCompra::class)
            ->fillForm([
                'proveedor_id' => $this->proveedor->id,
                'almacen_id' => $this->almacenB->id,
                'numero_factura' => 'F001-1',
                'detalles' => [
                    ['producto_id' => $this->variante->producto_id, 'producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'unidad', 'cantidad' => 10, 'precio_compra_unitario' => '1.50'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('compras', [
            'proveedor_id' => $this->proveedor->id,
            'almacen_id' => $this->almacenB->id,
            'usuario_id' => $admin->id,
            'estado' => CompraEstado::Completado->value,
        ]);

        $inventario = Inventario::where('almacen_id', $this->almacenB->id)
            ->where('producto_variante_id', $this->variante->id)
            ->first();

        $this->assertSame(10, $inventario->cantidad);
    }

    public function test_almacenero_con_un_solo_almacen_lo_ve_precargado(): void
    {
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almacenero->almacenes()->attach($this->almacenA->id);

        $this->actingAs($almacenero);
        Filament::setCurrentPanel('almacen');

        $component = Livewire::test(AlmacenCreateCompra::class);

        $this->assertEquals($this->almacenA->id, data_get($component->get('data'), 'almacen_id'));
    }

    public function test_almacenero_puede_registrar_una_compra_para_su_propio_almacen(): void
    {
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almacenero->almacenes()->attach($this->almacenA->id);

        $this->actingAs($almacenero);
        Filament::setCurrentPanel('almacen');

        Livewire::test(AlmacenCreateCompra::class)
            ->fillForm([
                'proveedor_id' => $this->proveedor->id,
                'almacen_id' => $this->almacenA->id,
                'detalles' => [
                    ['producto_id' => $this->variante->producto_id, 'producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'unidad', 'cantidad' => 5, 'precio_compra_unitario' => '1.00'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('compras', [
            'almacen_id' => $this->almacenA->id,
            'usuario_id' => $almacenero->id,
        ]);
    }

    public function test_almacenero_no_puede_registrar_una_compra_para_un_almacen_que_no_le_pertenece(): void
    {
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almacenero->almacenes()->attach([$this->almacenA->id, $this->almacenB->id]);

        $otraSucursal = Sucursal::create(['nombre' => 'Otra Sucursal', 'estado' => true]);
        $almacenAjeno = Almacen::create([
            'sucursal_id' => $otraSucursal->id,
            'nombre' => 'Depósito Ajeno',
            'tipo' => 'deposito',
            'estado' => true,
        ]);

        $this->actingAs($almacenero);
        Filament::setCurrentPanel('almacen');

        // Primera capa de defensa: el propio Select de almacén ya solo ofrece los
        // almacenes asignados como opciones válidas, así que Filament rechaza el envío
        // a nivel de validación de formulario antes de siquiera llegar a
        // handleRecordCreation() (donde vive la segunda capa, el abort_unless()).
        Livewire::test(AlmacenCreateCompra::class)
            ->fillForm([
                'proveedor_id' => $this->proveedor->id,
                'almacen_id' => $almacenAjeno->id,
                'detalles' => [
                    ['producto_id' => $this->variante->producto_id, 'producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'unidad', 'cantidad' => 5, 'precio_compra_unitario' => '1.00'],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['almacen_id']);

        $this->assertDatabaseMissing('compras', ['almacen_id' => $almacenAjeno->id]);
    }

    public function test_seleccionar_variante_en_una_linea_precarga_las_unidades_por_caja(): void
    {
        $this->variante->update(['unidades_por_caja' => 24]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $component = Livewire::test(AdminCreateCompra::class)
            ->set('data.detalles.0.producto_variante_id', $this->variante->id);

        $this->assertEquals(24, data_get($component->get('data'), 'detalles.0.unidades_por_caja'));
    }

    public function test_solo_admin_ve_la_accion_anular_en_la_tabla(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almacenero->almacenes()->attach($this->almacenA->id);

        $compra = Compra::create([
            'proveedor_id' => $this->proveedor->id,
            'almacen_id' => $this->almacenA->id,
            'usuario_id' => $almacenero->id,
            'total' => 15,
            'estado' => CompraEstado::Completado,
        ]);

        $this->assertTrue($admin->can('anular', $compra));
        $this->assertFalse($almacenero->can('anular', $compra));
    }

    public function test_admin_puede_anular_una_compra_desde_la_tabla_y_revierte_el_stock(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(AdminCreateCompra::class)
            ->fillForm([
                'proveedor_id' => $this->proveedor->id,
                'almacen_id' => $this->almacenA->id,
                'detalles' => [
                    ['producto_id' => $this->variante->producto_id, 'producto_variante_id' => $this->variante->id, 'modo_cantidad' => 'unidad', 'cantidad' => 10, 'precio_compra_unitario' => '1.50'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $compra = Compra::firstOrFail();

        Livewire::test(ListCompras::class)
            ->callTableAction('anular', $compra)
            ->assertHasNoTableActionErrors();

        $this->assertSame(CompraEstado::Anulado, $compra->fresh()->estado);

        $inventario = Inventario::where('almacen_id', $this->almacenA->id)
            ->where('producto_variante_id', $this->variante->id)
            ->first();

        $this->assertSame(0, $inventario->cantidad);
    }
}
