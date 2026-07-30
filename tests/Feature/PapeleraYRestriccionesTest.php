<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Marcas\Pages\EditMarca;
use App\Filament\Resources\Productos\Pages\EditProducto;
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
 * Paso 3.7: papelera (soft delete) con restricciones de negocio, cascada de
 * entidades hijas, y manejo amigable de la restricción de FK al forzar la
 * eliminación definitiva de un registro con historial protegido.
 */
class PapeleraYRestriccionesTest extends TestCase
{
    use RefreshDatabase;

    private function crearVariante(?Categoria $categoria = null, ?Marca $marca = null): ProductoVariante
    {
        $categoria ??= Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos']);

        $producto = Producto::create([
            'nombre' => 'Cuaderno 100 hojas',
            'slug' => 'cuaderno-100-hojas',
            'categoria_id' => $categoria->id,
            'marca_id' => $marca?->id,
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

    // --- Lógica de negocio en los modelos ---

    public function test_marca_tiene_productos_activos_solo_si_hay_productos_no_eliminados(): void
    {
        $marca = Marca::create(['nombre' => 'Norma', 'slug' => 'norma']);
        $this->assertFalse($marca->tieneProductosActivos());

        $variante = $this->crearVariante(marca: $marca);
        $this->assertTrue($marca->fresh()->tieneProductosActivos());

        $variante->producto->delete();
        $this->assertFalse($marca->fresh()->tieneProductosActivos());
    }

    public function test_categoria_tiene_productos_activos_solo_si_hay_productos_no_eliminados(): void
    {
        $categoria = Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos']);
        $this->assertFalse($categoria->tieneProductosActivos());

        $this->crearVariante($categoria);
        $this->assertTrue($categoria->fresh()->tieneProductosActivos());
    }

    public function test_almacen_tiene_stock_fisico_solo_si_alguna_cantidad_es_mayor_a_cero(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal A', 'estado' => true]);
        $almacen = Almacen::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Depósito', 'tipo' => 'deposito', 'estado' => true]);
        $variante = $this->crearVariante();

        $this->assertFalse($almacen->tieneStockFisico());

        Inventario::create([
            'almacen_id' => $almacen->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 0,
            'cantidad_comprometida' => 0,
            'stock_minimo' => 5,
        ]);
        $this->assertFalse($almacen->fresh()->tieneStockFisico(), 'Una fila de inventario en cero no cuenta como stock físico.');

        Inventario::where('almacen_id', $almacen->id)->update(['cantidad' => 3]);
        $this->assertTrue($almacen->fresh()->tieneStockFisico());
    }

    public function test_sucursal_tiene_stock_fisico_si_alguno_de_sus_almacenes_tiene_stock(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal A', 'estado' => true]);
        // El Observer ya crea un almacén "tienda" automáticamente al crear la sucursal.
        $deposito = Almacen::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Depósito', 'tipo' => 'deposito', 'estado' => true]);
        $variante = $this->crearVariante();

        $this->assertFalse($sucursal->fresh()->tieneStockFisico());

        Inventario::create([
            'almacen_id' => $deposito->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 4,
            'cantidad_comprometida' => 0,
            'stock_minimo' => 5,
        ]);

        $this->assertTrue($sucursal->fresh()->tieneStockFisico());
    }

    // --- Cascada de papelera vía Observers ---

    public function test_eliminar_un_producto_cascada_la_papelera_a_sus_variantes(): void
    {
        $variante = $this->crearVariante();
        $producto = $variante->producto;

        $producto->delete();

        $this->assertNotNull($producto->fresh()->deleted_at);
        $this->assertNotNull($variante->fresh()->deleted_at, 'La variante debe quedar en la papelera junto con su producto.');
    }

    public function test_eliminar_una_sucursal_sin_stock_cascada_la_papelera_a_sus_almacenes(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal A', 'estado' => true]);
        $almacenAutomatico = $sucursal->almacenes()->first();
        $this->assertNotNull($almacenAutomatico, 'El Observer de Fase 3.5 debe haber creado el almacén tienda.');

        $sucursal->delete();

        $this->assertNotNull($sucursal->fresh()->deleted_at);
        $this->assertNotNull($almacenAutomatico->fresh()->deleted_at, 'El almacén debe quedar en la papelera junto con su sucursal.');
    }

    // --- Guardas de negocio en la UI (Filament) ---

    public function test_no_se_puede_enviar_una_marca_con_productos_activos_a_la_papelera(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $marca = Marca::create(['nombre' => 'Norma', 'slug' => 'norma']);
        $this->crearVariante(marca: $marca);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(EditMarca::class, ['record' => $marca->getRouteKey()])
            ->callAction('delete')
            ->assertActionHalted('delete');

        $this->assertNull($marca->fresh()->deleted_at, 'La marca no debe quedar en la papelera mientras tenga productos activos.');
    }

    public function test_se_puede_enviar_una_marca_sin_productos_a_la_papelera(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $marca = Marca::create(['nombre' => 'Norma', 'slug' => 'norma']);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(EditMarca::class, ['record' => $marca->getRouteKey()])
            ->callAction('delete');

        $this->assertNotNull($marca->fresh()->deleted_at);
    }

    public function test_forzar_eliminacion_de_producto_con_variantes_no_revienta_y_avisa_amigablemente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $variante = $this->crearVariante();
        $producto = $variante->producto;
        $producto->delete();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        // `producto_variantes.producto_id` usa `restrictOnDelete()`: Postgres debe
        // rechazar el forceDelete mientras la variante (aunque esté en la papelera)
        // siga existiendo físicamente en la tabla.
        Livewire::test(EditProducto::class, ['record' => $producto->getRouteKey()])
            ->callAction('forceDelete')
            ->assertActionHalted('forceDelete');

        $this->assertNotNull(Producto::onlyTrashed()->find($producto->id), 'El producto debe seguir existiendo (solo en la papelera), no se pudo forzar su eliminación.');
    }
}
