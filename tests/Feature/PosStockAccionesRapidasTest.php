<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pos\Resources\Inventarios\Pages\ListInventarios;
use App\Filament\Pos\Resources\Traspasos\Pages\CreateTraspaso;
use App\Filament\Pos\Resources\Traspasos\TraspasoResource;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cubre las mejoras pedidas sobre la ventana de stock del Vendedor (panel `pos`):
 * (1) la tabla ahora muestra marca/categoría/atributos de la variante, no solo su
 * código, y (2) dos atajos hacia "Nueva solicitud de traspaso" —un botón por fila
 * y una selección múltiple— que precargan el formulario sin obligar a repetir la
 * búsqueda de producto. Ninguno de los dos toca `RegistrarMovimientoInventarioAction`
 * ni el Kardex: son solo conveniencias de UI sobre el flujo ya existente.
 */
class PosStockAccionesRapidasTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Almacen $tienda;

    private Almacen $depositoCentral;

    private Almacen $depositoNorte;

    private ProductoVariante $varianteA;

    private ProductoVariante $varianteB;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursal = Sucursal::create(['nombre' => 'Sucursal Central', 'estado' => true]);
        $this->tienda = $this->sucursal->almacenes()->first();

        $this->depositoCentral = Almacen::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Depósito Central',
            'tipo' => 'deposito',
            'estado' => true,
        ]);

        $this->depositoNorte = Almacen::create([
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Depósito Norte',
            'tipo' => 'deposito',
            'estado' => true,
        ]);

        $marca = Marca::create(['nombre' => 'Faber-Castell', 'slug' => 'faber-castell']);
        $categoria = Categoria::create(['nombre' => 'Escritura', 'slug' => 'escritura']);
        $producto = Producto::create([
            'nombre' => 'Lapicero Punta Fina',
            'slug' => 'lapicero-punta-fina',
            'marca_id' => $marca->id,
            'categoria_id' => $categoria->id,
            'estado' => true,
        ]);

        $this->varianteA = ProductoVariante::create([
            'producto_id' => $producto->id,
            'codigo_interno' => 'LPF-AZUL',
            'atributos' => ['color' => 'Azul'],
            'costo_real' => 0.70,
            'precio_venta_unidad' => 0.90,
            'precio_venta_docena' => 0.80,
            'precio_venta_mayor' => 0.75,
            'estado' => true,
        ]);

        $this->varianteB = ProductoVariante::create([
            'producto_id' => $producto->id,
            'codigo_interno' => 'LPF-ROJO',
            'atributos' => ['color' => 'Rojo'],
            'costo_real' => 0.70,
            'precio_venta_unidad' => 0.90,
            'precio_venta_docena' => 0.80,
            'precio_venta_mayor' => 0.75,
            'estado' => true,
        ]);

        Inventario::create([
            'almacen_id' => $this->tienda->id,
            'producto_variante_id' => $this->varianteA->id,
            'cantidad' => 10,
        ]);

        Inventario::create([
            'almacen_id' => $this->depositoCentral->id,
            'producto_variante_id' => $this->varianteA->id,
            'cantidad' => 50,
        ]);

        Inventario::create([
            'almacen_id' => $this->depositoNorte->id,
            'producto_variante_id' => $this->varianteB->id,
            'cantidad' => 30,
        ]);

        $this->vendedor = User::factory()->create([
            'role' => UserRole::Vendedor,
            'is_active' => true,
            'sucursal_id' => $this->sucursal->id,
        ]);
    }

    public function test_la_tabla_de_stock_muestra_marca_categoria_y_atributos_de_la_variante(): void
    {
        $this->actingAs($this->vendedor)
            ->get('/pos/inventarios')
            ->assertSuccessful()
            ->assertSee('Faber-Castell')
            ->assertSee('Escritura')
            ->assertSee('Color: Azul', escape: false);
    }

    public function test_el_boton_solicitar_aparece_tanto_en_mi_sucursal_como_en_almacenes(): void
    {
        $filaTienda = Inventario::where('almacen_id', $this->tienda->id)->firstOrFail();
        $filaDeposito = Inventario::where('almacen_id', $this->depositoCentral->id)->firstOrFail();

        $this->actingAs($this->vendedor);
        Filament::setCurrentPanel('pos');

        Livewire::test(ListInventarios::class)
            ->assertTableActionVisible('solicitarTraspaso', record: $filaTienda)
            ->assertTableActionVisible('solicitarTraspaso', record: $filaDeposito);
    }

    public function test_el_boton_solicitar_en_una_fila_de_deposito_precarga_tambien_el_almacen_origen(): void
    {
        $filaDeposito = Inventario::where('almacen_id', $this->depositoCentral->id)->firstOrFail();

        $this->actingAs($this->vendedor);
        Filament::setCurrentPanel('pos');

        $urlEsperada = TraspasoResource::getUrl('create', [
            'almacen_origen_id' => $this->depositoCentral->id,
            'producto_variante_id' => $this->varianteA->id,
        ]);

        Livewire::test(ListInventarios::class)
            ->assertTableActionHasUrl('solicitarTraspaso', $urlEsperada, record: $filaDeposito);
    }

    public function test_el_boton_solicitar_en_una_fila_de_mi_sucursal_no_precarga_almacen_origen(): void
    {
        $filaTienda = Inventario::where('almacen_id', $this->tienda->id)->firstOrFail();

        $this->actingAs($this->vendedor);
        Filament::setCurrentPanel('pos');

        // La tienda propia nunca es un origen válido de traspaso: se precarga el
        // producto, pero el Vendedor elige el depósito de origen en el formulario.
        $urlEsperada = TraspasoResource::getUrl('create', [
            'producto_variante_id' => $this->varianteA->id,
        ]);

        Livewire::test(ListInventarios::class)
            ->assertTableActionHasUrl('solicitarTraspaso', $urlEsperada, record: $filaTienda);
    }

    public function test_llegar_desde_el_boton_solicitar_permite_crear_el_traspaso_solo_confirmando(): void
    {
        $this->actingAs($this->vendedor);
        Filament::setCurrentPanel('pos');

        // Sin fillForm(): el formulario debe quedar armado solo con los defaults
        // que lee de la query string (origen + producto) más el destino, que se
        // auto-completa con la única tienda de la sucursal del vendedor.
        Livewire::withQueryParams([
            'almacen_origen_id' => (string) $this->depositoCentral->id,
            'producto_variante_id' => (string) $this->varianteA->id,
        ])->test(CreateTraspaso::class)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('traspasos', [
            'almacen_origen_id' => $this->depositoCentral->id,
            'almacen_destino_id' => $this->tienda->id,
            'usuario_solicitante_id' => $this->vendedor->id,
        ]);

        $this->assertDatabaseHas('traspaso_detalles', [
            'producto_variante_id' => $this->varianteA->id,
            'cantidad' => 1,
        ]);
    }

    public function test_la_solicitud_manual_sin_query_string_sigue_arrancando_con_una_fila_vacia(): void
    {
        $this->actingAs($this->vendedor);
        Filament::setCurrentPanel('pos');

        // Sin producto/variante precargados, la fila vacía obliga a elegir producto
        // antes de poder guardar — mismo comportamiento que antes de este cambio.
        Livewire::test(CreateTraspaso::class)
            ->call('create')
            ->assertHasErrors();
    }

    public function test_seleccion_multiple_de_depositos_distintos_redirige_sin_precargar_origen(): void
    {
        $filaDepositoCentral = Inventario::where('almacen_id', $this->depositoCentral->id)->firstOrFail();
        $filaDepositoNorte = Inventario::where('almacen_id', $this->depositoNorte->id)->firstOrFail();

        $this->actingAs($this->vendedor);
        Filament::setCurrentPanel('pos');

        // Ambiguo (dos depósitos distintos): no hay forma de saber cuál origen
        // precargar, así que redirige igual pero deja que el Vendedor lo elija.
        $componente = Livewire::test(ListInventarios::class)
            ->callTableBulkAction('solicitarTraspasoMasivo', [$filaDepositoCentral, $filaDepositoNorte])
            ->assertRedirectContains('/pos/traspasos/create')
            ->assertRedirectContains('variantes=');

        $this->assertStringNotContainsString('almacen_origen_id', $componente->effects['redirect']);
    }

    public function test_seleccion_multiple_del_mismo_deposito_redirige_al_alta_con_las_variantes_precargadas(): void
    {
        Inventario::create([
            'almacen_id' => $this->depositoCentral->id,
            'producto_variante_id' => $this->varianteB->id,
            'cantidad' => 15,
        ]);

        $filaVarianteA = Inventario::where('almacen_id', $this->depositoCentral->id)
            ->where('producto_variante_id', $this->varianteA->id)
            ->firstOrFail();
        $filaVarianteB = Inventario::where('almacen_id', $this->depositoCentral->id)
            ->where('producto_variante_id', $this->varianteB->id)
            ->firstOrFail();

        $this->actingAs($this->vendedor);
        Filament::setCurrentPanel('pos');

        Livewire::test(ListInventarios::class)
            ->callTableBulkAction('solicitarTraspasoMasivo', [$filaVarianteA, $filaVarianteB])
            ->assertRedirectContains('/pos/traspasos/create')
            ->assertRedirectContains("almacen_origen_id={$this->depositoCentral->id}");
    }

    public function test_seleccion_multiple_de_productos_bajos_en_mi_sucursal_redirige_sin_precargar_origen(): void
    {
        $filaTienda = Inventario::where('almacen_id', $this->tienda->id)
            ->where('producto_variante_id', $this->varianteA->id)
            ->firstOrFail();

        $this->actingAs($this->vendedor);
        Filament::setCurrentPanel('pos');

        // Este es el caso pedido por el Vendedor: seleccionar en la pestaña "Mi
        // sucursal" (ej. los productos en rojo por stock bajo) y que lo mande
        // directo a pedir el traspaso, eligiendo el depósito de origen allá.
        $componente = Livewire::test(ListInventarios::class)
            ->callTableBulkAction('solicitarTraspasoMasivo', [$filaTienda])
            ->assertRedirectContains('/pos/traspasos/create')
            ->assertRedirectContains("variantes={$this->varianteA->id}");

        $this->assertStringNotContainsString('almacen_origen_id', $componente->effects['redirect']);
    }
}
