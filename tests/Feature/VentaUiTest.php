<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Ventas\CrearPreventaAction;
use App\Enums\UserRole;
use App\Enums\VentaEstado;
use App\Enums\VentaMetodoPago;
use App\Filament\Pos\Resources\Ventas\Pages\CreateVenta;
use App\Filament\Pos\Resources\Ventas\Pages\ListVentas;
use App\Filament\Pos\Resources\Ventas\Tables\VentasTable;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\ListaEscolar;
use App\Models\ListaEscolarDetalle;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Tests\TestCase;

class VentaUiTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    private ProductoVariante $variante;

    protected function setUp(): void
    {
        parent::setUp();

        // SucursalObserver (Paso 3.5) crea automáticamente el almacén 'tienda' de cada una.
        $this->sucursalA = Sucursal::create(['nombre' => 'Sucursal A', 'estado' => true]);
        $this->sucursalB = Sucursal::create(['nombre' => 'Sucursal B', 'estado' => true]);

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
            'almacen_id' => $this->sucursalA->almacenTienda()->id,
            'producto_variante_id' => $this->variante->id,
            'cantidad' => 10,
        ]);
    }

    public function test_vendedor_puede_crear_una_preventa_desde_el_formulario(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($vendedorA);
        Filament::setCurrentPanel('pos');

        Livewire::test(CreateVenta::class)
            ->fillForm([
                'cliente_temporal' => 'Juan Polera Roja',
                'detalles' => [
                    ['producto_variante_id' => $this->variante->id, 'cantidad' => 2],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('ventas', [
            'sucursal_id' => $this->sucursalA->id,
            'vendedor_id' => $vendedorA->id,
            'cliente_temporal' => 'Juan Polera Roja',
            'estado' => VentaEstado::Pendiente->value,
        ]);

        $this->assertDatabaseHas('venta_detalles', [
            'producto_variante_id' => $this->variante->id,
            'cantidad' => 2,
        ]);
    }

    public function test_vendedor_debe_indicar_cliente_registrado_o_temporal(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($vendedorA);
        Filament::setCurrentPanel('pos');

        Livewire::test(CreateVenta::class)
            ->fillForm([
                'detalles' => [
                    ['producto_variante_id' => $this->variante->id, 'cantidad' => 2],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['cliente_id', 'cliente_temporal']);
    }

    public function test_cajero_no_puede_crear_preventas(): void
    {
        $cajero = User::factory()->create(['role' => UserRole::Cajero, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($cajero)
            ->get('/pos/ventas/create')
            ->assertForbidden();
    }

    public function test_vendedor_solo_ve_ventas_de_su_propia_sucursal(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);
        $vendedorB = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalB->id]);

        Venta::create([
            'sucursal_id' => $this->sucursalA->id,
            'vendedor_id' => $vendedorA->id,
            'cliente_temporal' => 'Cliente Sucursal A',
            'total' => 10,
            'estado' => VentaEstado::Pendiente,
        ]);

        Venta::create([
            'sucursal_id' => $this->sucursalB->id,
            'vendedor_id' => $vendedorB->id,
            'cliente_temporal' => 'Cliente Sucursal B',
            'total' => 20,
            'estado' => VentaEstado::Pendiente,
        ]);

        $this->actingAs($vendedorA)
            ->get('/pos/ventas')
            ->assertSuccessful()
            ->assertSee('Cliente Sucursal A')
            ->assertDontSee('Cliente Sucursal B');

        $this->actingAs($vendedorB)
            ->get('/pos/ventas')
            ->assertSuccessful()
            ->assertSee('Cliente Sucursal B')
            ->assertDontSee('Cliente Sucursal A');
    }

    public function test_boton_cobrar_solo_lo_ve_el_cajero_y_cancelar_solo_el_vendedor(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);
        $cajeroA = User::factory()->create(['role' => UserRole::Cajero, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        Venta::create([
            'sucursal_id' => $this->sucursalA->id,
            'vendedor_id' => $vendedorA->id,
            'cliente_temporal' => 'Juan',
            'total' => 10,
            'estado' => VentaEstado::Pendiente,
        ]);

        $this->actingAs($cajeroA)
            ->get('/pos/ventas')
            ->assertSuccessful()
            ->assertSee('Cobrar')
            ->assertDontSee('Cancelar');

        $this->actingAs($vendedorA)
            ->get('/pos/ventas')
            ->assertSuccessful()
            ->assertSee('Cancelar')
            ->assertDontSee('Cobrar');
    }

    public function test_cajero_puede_cobrar_una_preventa_pendiente_desde_la_tabla(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);
        $cajeroA = User::factory()->create(['role' => UserRole::Cajero, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        // Vía la Action real (no Venta::create() directo): así cantidad_comprometida
        // queda correctamente reservada, como en el flujo real Vendedor -> Cajero.
        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $this->sucursalA->id,
            'vendedor_id' => $vendedorA->id,
            'cliente_temporal' => 'Juan',
            'detalles' => [
                ['producto_variante_id' => $this->variante->id, 'cantidad' => 2],
            ],
        ]);

        $this->actingAs($cajeroA);
        Filament::setCurrentPanel('pos');

        Livewire::test(ListVentas::class)
            ->mountTableAction('cobrar', $venta)
            ->setTableActionData(['metodo_pago' => VentaMetodoPago::Efectivo->value])
            ->assertHasNoTableActionErrors()
            ->callMountedTableAction()
            ->assertSuccessful()
            ->assertHasNoTableActionErrors();

        $this->assertSame(VentaEstado::Completado, $venta->fresh()->estado);
        $this->assertSame($cajeroA->id, $venta->fresh()->usuario_id);

        $inventario = Inventario::where('almacen_id', $this->sucursalA->almacenTienda()->id)
            ->where('producto_variante_id', $this->variante->id)
            ->first();

        $this->assertSame(8, $inventario->cantidad, 'Debe descontar el stock real (10 - 2).');
    }

    public function test_vendedor_no_puede_cobrar_una_preventa(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $venta = Venta::create([
            'sucursal_id' => $this->sucursalA->id,
            'vendedor_id' => $vendedorA->id,
            'cliente_temporal' => 'Juan',
            'total' => 13,
            'estado' => VentaEstado::Pendiente,
        ]);

        $this->assertFalse($vendedorA->can('cobrar', $venta));
    }

    public function test_buscador_de_producto_agrega_una_fila_a_la_canasta(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($vendedorA);
        Filament::setCurrentPanel('pos');

        $component = Livewire::test(CreateVenta::class)
            ->set('data.buscador_producto', $this->variante->id);

        $detalles = data_get($component->get('data'), 'detalles', []);

        $this->assertCount(1, $detalles, 'El buscador debe agregar automáticamente una fila a la canasta.');
        $this->assertSame($this->variante->id, (int) Arr::first($detalles)['producto_variante_id']);
        $this->assertSame(1, (int) Arr::first($detalles)['cantidad'], 'La primera vez que se busca un producto, la cantidad debe iniciar en 1.');
        $this->assertNull(data_get($component->get('data'), 'buscador_producto'), 'El buscador debe limpiarse tras agregar el producto.');

        // Buscar el mismo producto otra vez debe incrementar la cantidad, no duplicar la fila.
        $component->set('data.buscador_producto', $this->variante->id);

        $detalles = data_get($component->get('data'), 'detalles', []);
        $this->assertCount(1, $detalles, 'Buscar el mismo producto dos veces no debe crear una segunda fila.');
        $this->assertSame(2, (int) Arr::first($detalles)['cantidad'], 'La segunda búsqueda del mismo producto debe incrementar la cantidad existente.');

        $component
            ->fillForm(['cliente_temporal' => 'Juan Polera Roja'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('venta_detalles', [
            'producto_variante_id' => $this->variante->id,
            'cantidad' => 2,
        ]);
    }

    public function test_aplicar_lista_escolar_agrega_todas_sus_lineas_a_la_canasta(): void
    {
        $lapiz = ProductoVariante::create([
            'producto_id' => $this->variante->producto_id,
            'codigo_interno' => 'LAP-001',
            'costo_real' => 1.00,
            'precio_venta_unidad' => 1.20,
            'precio_venta_docena' => 1.10,
            'precio_venta_mayor' => 1.00,
            'estado' => true,
        ]);

        $lista = ListaEscolar::create(['nombre_plantilla' => '1ro Básico', 'colegio' => 'San Calixto']);
        ListaEscolarDetalle::create(['lista_escolar_id' => $lista->id, 'producto_variante_id' => $this->variante->id, 'cantidad' => 3]);
        ListaEscolarDetalle::create(['lista_escolar_id' => $lista->id, 'producto_variante_id' => $lapiz->id, 'cantidad' => 2]);

        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($vendedorA);
        Filament::setCurrentPanel('pos');

        $component = Livewire::test(CreateVenta::class)
            ->set('data.aplicar_lista_escolar', $lista->id);

        $detalles = data_get($component->get('data'), 'detalles', []);
        $this->assertCount(2, $detalles, 'Debe agregar una fila por cada línea de la plantilla.');

        $cantidadesPorVariante = collect($detalles)->pluck('cantidad', 'producto_variante_id');
        $this->assertSame(3, (int) $cantidadesPorVariante[$this->variante->id]);
        $this->assertSame(2, (int) $cantidadesPorVariante[$lapiz->id]);

        // Aplicar la misma plantilla otra vez debe sumar cantidades, no duplicar filas.
        $component->set('data.aplicar_lista_escolar', $lista->id);

        $detalles = data_get($component->get('data'), 'detalles', []);
        $this->assertCount(2, $detalles, 'Reaplicar la misma plantilla no debe duplicar filas.');

        $cantidadesPorVariante = collect($detalles)->pluck('cantidad', 'producto_variante_id');
        $this->assertSame(6, (int) $cantidadesPorVariante[$this->variante->id]);
        $this->assertSame(4, (int) $cantidadesPorVariante[$lapiz->id]);
    }

    public function test_aplicar_lista_escolar_omite_lineas_de_productos_descontinuados(): void
    {
        $descontinuado = ProductoVariante::create([
            'producto_id' => $this->variante->producto_id,
            'codigo_interno' => 'DISC-001',
            'costo_real' => 1.00,
            'precio_venta_unidad' => 1.20,
            'precio_venta_docena' => 1.10,
            'precio_venta_mayor' => 1.00,
            'estado' => false,
        ]);

        $lista = ListaEscolar::create(['nombre_plantilla' => '1ro Básico']);
        ListaEscolarDetalle::create(['lista_escolar_id' => $lista->id, 'producto_variante_id' => $this->variante->id, 'cantidad' => 3]);
        ListaEscolarDetalle::create(['lista_escolar_id' => $lista->id, 'producto_variante_id' => $descontinuado->id, 'cantidad' => 1]);

        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $this->actingAs($vendedorA);
        Filament::setCurrentPanel('pos');

        $component = Livewire::test(CreateVenta::class)
            ->set('data.aplicar_lista_escolar', $lista->id);

        $detalles = data_get($component->get('data'), 'detalles', []);
        $this->assertCount(1, $detalles, 'La línea del producto descontinuado debe omitirse.');
        $this->assertSame($this->variante->id, (int) Arr::first($detalles)['producto_variante_id']);
    }

    public function test_cajero_puede_canjear_puntos_al_cobrar_desde_la_tabla(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);
        $cajeroA = User::factory()->create(['role' => UserRole::Cajero, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);
        $cliente = Cliente::create(['nombres' => 'Ana', 'apellidos' => 'Ruiz', 'puntos_acumulados' => 10]);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $this->sucursalA->id,
            'vendedor_id' => $vendedorA->id,
            'cliente_id' => $cliente->id,
            'detalles' => [
                ['producto_variante_id' => $this->variante->id, 'cantidad' => 2],
            ],
        ]);

        $this->actingAs($cajeroA);
        Filament::setCurrentPanel('pos');

        Livewire::test(ListVentas::class)
            ->mountTableAction('cobrar', $venta)
            ->setTableActionData([
                'metodo_pago' => VentaMetodoPago::Efectivo->value,
                'puntos_utilizados' => 5,
            ])
            ->assertHasNoTableActionErrors()
            ->callMountedTableAction()
            ->assertSuccessful()
            ->assertHasNoTableActionErrors();

        $venta->refresh();
        $this->assertSame(5, $venta->puntos_utilizados);
        $this->assertSame('1.50', (string) $venta->descuento_por_puntos);
        $this->assertSame(5, $cliente->fresh()->puntos_acumulados, '10 iniciales - 5 canjeados + 0 ganados sobre el total final (11.50 < 30).');
    }

    /**
     * Nota de testing: el modal "Cobrar" carga su contenido vía un partial diferido de
     * Filament (`wire:partial="action-modals"`), que no aparece en el snapshot de
     * `Livewire::test()` — por eso el resumen de productos y el cálculo de vuelto se
     * prueban llamando directamente a los helpers puros de `VentasTable` (mismo criterio
     * que el resto del proyecto: los textos de preview de un formulario no se prueban vía
     * render, solo el comportamiento funcional real — ver `VentaForm::totalEstimado()`,
     * nunca probado vía `assertSee`).
     */
    public function test_el_resumen_de_productos_lista_cada_linea_con_su_subtotal(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $this->sucursalA->id,
            'vendedor_id' => $vendedorA->id,
            'cliente_temporal' => 'Juan',
            'detalles' => [
                ['producto_variante_id' => $this->variante->id, 'cantidad' => 2],
            ],
        ]);

        $html = (string) VentasTable::resumenProductos($venta);

        $this->assertStringContainsString('Cuaderno 100 hojas', $html);
        $this->assertStringContainsString('CUA-001', $html);
        $this->assertStringContainsString('× 2', $html);
        $this->assertStringContainsString('S/ 13.00', $html);
    }

    public function test_el_resumen_de_productos_avisa_si_la_venta_no_tiene_lineas(): void
    {
        $venta = Venta::create([
            'sucursal_id' => $this->sucursalA->id,
            'cliente_temporal' => 'Juan',
            'total' => 0,
            'estado' => VentaEstado::Pendiente,
        ]);

        $html = (string) VentasTable::resumenProductos($venta);

        $this->assertStringContainsString('no tiene productos', $html);
    }

    public function test_el_vuelto_se_calcula_correctamente_cuando_el_monto_recibido_alcanza(): void
    {
        // Total: 2 × 6.50 = 13.00
        $venta = Venta::make(['total' => 13.00]);

        $this->assertSame('Vuelto a devolver: S/ 7.00', VentasTable::previewVuelto('20', $venta));
        $this->assertSame('success', VentasTable::colorVuelto('20', $venta));

        // Monto exacto: vuelto en cero, sigue siendo un cobro válido.
        $this->assertSame('Vuelto a devolver: S/ 0.00', VentasTable::previewVuelto('13', $venta));
        $this->assertSame('success', VentasTable::colorVuelto('13', $venta));
    }

    public function test_el_vuelto_avisa_sin_bloquear_cuando_el_monto_recibido_no_alcanza(): void
    {
        // Total: 2 × 6.50 = 13.00
        $venta = Venta::make(['total' => 13.00]);

        $this->assertSame(
            'Falta S/ 3.00 — el monto recibido no cubre el total (S/ 13.00).',
            VentasTable::previewVuelto('10', $venta)
        );
        $this->assertSame('danger', VentasTable::colorVuelto('10', $venta));
    }

    public function test_el_vuelto_no_se_calcula_hasta_que_se_ingrese_un_monto(): void
    {
        $venta = Venta::make(['total' => 13.00]);

        $this->assertSame('Ingresa el monto recibido para calcular el vuelto.', VentasTable::previewVuelto(null, $venta));
        $this->assertSame('gray', VentasTable::colorVuelto(null, $venta));
    }

    public function test_el_cobro_en_efectivo_funciona_normalmente_con_el_monto_recibido_presente_en_el_formulario(): void
    {
        $vendedorA = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);
        $cajeroA = User::factory()->create(['role' => UserRole::Cajero, 'is_active' => true, 'sucursal_id' => $this->sucursalA->id]);

        $venta = (new CrearPreventaAction)->handle([
            'sucursal_id' => $this->sucursalA->id,
            'vendedor_id' => $vendedorA->id,
            'cliente_temporal' => 'Juan',
            'detalles' => [
                ['producto_variante_id' => $this->variante->id, 'cantidad' => 2],
            ],
        ]);

        $this->actingAs($cajeroA);
        Filament::setCurrentPanel('pos');

        // "monto_recibido" es efímero (dehydrated(false), no hay columna para esto en
        // `ventas`) — incluirlo en el envío no debe alterar el cierre real de la venta.
        Livewire::test(ListVentas::class)
            ->mountTableAction('cobrar', $venta)
            ->setTableActionData([
                'metodo_pago' => VentaMetodoPago::Efectivo->value,
                'monto_recibido' => '10',
            ])
            ->assertHasNoTableActionErrors()
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $venta->refresh();
        $this->assertSame(VentaEstado::Completado, $venta->estado);
        $this->assertSame('13.00', (string) $venta->total);
    }
}
