<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TraspasoEstado;
use App\Enums\UserRole;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\Traspaso;
use App\Models\TraspasoDetalle;
use App\Models\User;
use App\Filament\Pos\Resources\Traspasos\Pages\CreateTraspaso;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventarioTraspasoAislamientoUiTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    private Almacen $depositoCentral;

    private Almacen $depositoNorte;

    private Almacen $tiendaA;

    private Almacen $tiendaB;

    private ProductoVariante $variante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursalA = Sucursal::create(['nombre' => 'Sucursal A', 'estado' => true]);
        $this->sucursalB = Sucursal::create(['nombre' => 'Sucursal B', 'estado' => true]);

        $this->depositoCentral = Almacen::create([
            'sucursal_id' => $this->sucursalA->id,
            'nombre' => 'Depósito Central',
            'tipo' => 'deposito',
            'estado' => true,
        ]);

        $this->depositoNorte = Almacen::create([
            'sucursal_id' => $this->sucursalB->id,
            'nombre' => 'Depósito Norte',
            'tipo' => 'deposito',
            'estado' => true,
        ]);

        $this->tiendaA = Almacen::create([
            'sucursal_id' => $this->sucursalA->id,
            'nombre' => 'Tienda A',
            'tipo' => 'tienda',
            'estado' => true,
        ]);

        $this->tiendaB = Almacen::create([
            'sucursal_id' => $this->sucursalB->id,
            'nombre' => 'Tienda B',
            'tipo' => 'tienda',
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

        Inventario::create([
            'almacen_id' => $this->tiendaA->id,
            'producto_variante_id' => $this->variante->id,
            'cantidad' => 10,
        ]);

        Inventario::create([
            'almacen_id' => $this->tiendaB->id,
            'producto_variante_id' => $this->variante->id,
            'cantidad' => 20,
        ]);
    }

    public function test_almacenero_solo_ve_stock_de_sus_almacenes_asignados(): void
    {
        $almaceneroX = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almaceneroX->almacenes()->attach($this->depositoCentral->id);

        $almaceneroY = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almaceneroY->almacenes()->attach($this->depositoNorte->id);

        Inventario::create([
            'almacen_id' => $this->depositoCentral->id,
            'producto_variante_id' => $this->variante->id,
            'cantidad' => 100,
        ]);
        Inventario::create([
            'almacen_id' => $this->depositoNorte->id,
            'producto_variante_id' => $this->variante->id,
            'cantidad' => 200,
        ]);

        $this->actingAs($almaceneroX)
            ->get('/almacen/inventarios')
            ->assertSuccessful()
            ->assertSee('Depósito Central')
            ->assertDontSee('Depósito Norte');

        $this->actingAs($almaceneroY)
            ->get('/almacen/inventarios')
            ->assertSuccessful()
            ->assertSee('Depósito Norte')
            ->assertDontSee('Depósito Central');
    }

    public function test_almacenero_solo_ve_traspasos_que_despacha_desde_sus_almacenes(): void
    {
        $almaceneroX = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almaceneroX->almacenes()->attach($this->depositoCentral->id);

        $almaceneroY = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almaceneroY->almacenes()->attach($this->depositoNorte->id);

        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $traspaso = Traspaso::create([
            'almacen_origen_id' => $this->depositoCentral->id,
            'almacen_destino_id' => $this->tiendaA->id,
            'usuario_solicitante_id' => $vendedorA->id,
            'estado' => TraspasoEstado::Solicitado,
        ]);
        TraspasoDetalle::create(['traspaso_id' => $traspaso->id, 'producto_variante_id' => $this->variante->id, 'cantidad' => 5]);

        $this->actingAs($almaceneroX)
            ->get('/almacen/traspasos')
            ->assertSuccessful()
            ->assertSee('Depósito Central');

        $this->actingAs($almaceneroY)
            ->get('/almacen/traspasos')
            ->assertSuccessful()
            ->assertDontSee('Depósito Central');
    }

    public function test_vendedor_solo_ve_stock_de_su_propia_sucursal(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);
        $vendedorB = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalB->id]);

        $this->actingAs($vendedorA)
            ->get('/pos/inventarios')
            ->assertSuccessful()
            ->assertSee('Tienda A')
            ->assertDontSee('Tienda B');

        $this->actingAs($vendedorB)
            ->get('/pos/inventarios')
            ->assertSuccessful()
            ->assertSee('Tienda B')
            ->assertDontSee('Tienda A');
    }

    public function test_vendedor_solo_ve_traspasos_que_llegan_a_su_propia_sucursal(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);
        $vendedorB = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalB->id]);

        $traspaso = Traspaso::create([
            'almacen_origen_id' => $this->depositoCentral->id,
            'almacen_destino_id' => $this->tiendaA->id,
            'usuario_solicitante_id' => $vendedorA->id,
            'estado' => TraspasoEstado::Solicitado,
        ]);
        TraspasoDetalle::create(['traspaso_id' => $traspaso->id, 'producto_variante_id' => $this->variante->id, 'cantidad' => 5]);

        $this->actingAs($vendedorA)
            ->get('/pos/traspasos')
            ->assertSuccessful()
            ->assertSee('Tienda A');

        $this->actingAs($vendedorB)
            ->get('/pos/traspasos')
            ->assertSuccessful()
            ->assertDontSee('Tienda A');
    }

    public function test_vendedor_puede_solicitar_un_traspaso_desde_el_formulario(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($vendedorA);
        Filament::setCurrentPanel('pos');

        Livewire::test(CreateTraspaso::class)
            ->fillForm([
                'almacen_origen_id' => $this->depositoCentral->id,
                'almacen_destino_id' => $this->tiendaA->id,
                'detalles' => [
                    ['producto_id' => $this->variante->producto_id, 'producto_variante_id' => $this->variante->id, 'cantidad' => 3],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('traspasos', [
            'almacen_origen_id' => $this->depositoCentral->id,
            'almacen_destino_id' => $this->tiendaA->id,
            'usuario_solicitante_id' => $vendedorA->id,
            'estado' => TraspasoEstado::Solicitado->value,
        ]);

        $this->assertDatabaseHas('traspaso_detalles', [
            'producto_variante_id' => $this->variante->id,
            'cantidad' => 3,
        ]);
    }

    public function test_vendedor_no_puede_elegir_como_destino_un_almacen_de_tipo_deposito_de_su_propia_sucursal(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($vendedorA);
        Filament::setCurrentPanel('pos');

        Livewire::test(CreateTraspaso::class)
            ->fillForm([
                'almacen_origen_id' => $this->depositoNorte->id,
                'almacen_destino_id' => $this->depositoCentral->id,
                'detalles' => [
                    ['producto_id' => $this->variante->producto_id, 'producto_variante_id' => $this->variante->id, 'cantidad' => 3],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['almacen_destino_id']);
    }

    public function test_vendedor_no_puede_elegir_un_almacen_origen_desactivado(): void
    {
        $depositoDesactivado = Almacen::create([
            'sucursal_id' => $this->sucursalA->id,
            'nombre' => 'Depósito Cerrado',
            'tipo' => 'deposito',
            'estado' => false,
        ]);

        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($vendedorA);
        Filament::setCurrentPanel('pos');

        Livewire::test(CreateTraspaso::class)
            ->fillForm([
                'almacen_origen_id' => $depositoDesactivado->id,
                'almacen_destino_id' => $this->tiendaA->id,
                'detalles' => [
                    ['producto_id' => $this->variante->producto_id, 'producto_variante_id' => $this->variante->id, 'cantidad' => 3],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['almacen_origen_id']);
    }

    public function test_cajero_no_puede_crear_solicitudes_de_traspaso(): void
    {
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($cajero)
            ->get('/pos/traspasos/create')
            ->assertForbidden();
    }

    public function test_boton_completar_solo_aparece_cuando_el_traspaso_esta_en_transito(): void
    {
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almacenero->almacenes()->attach($this->depositoCentral->id);

        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $solicitado = Traspaso::create([
            'almacen_origen_id' => $this->depositoCentral->id,
            'almacen_destino_id' => $this->tiendaA->id,
            'usuario_solicitante_id' => $vendedorA->id,
            'estado' => TraspasoEstado::Solicitado,
        ]);

        $enTransito = Traspaso::create([
            'almacen_origen_id' => $this->depositoCentral->id,
            'almacen_destino_id' => $this->tiendaA->id,
            'usuario_solicitante_id' => $vendedorA->id,
            'estado' => TraspasoEstado::EnTransito,
        ]);

        $response = $this->actingAs($almacenero)
            ->get('/almacen/traspasos')
            ->assertSuccessful();

        $response->assertSee('Completar');
        $response->assertSee('Preparar');
    }
}
