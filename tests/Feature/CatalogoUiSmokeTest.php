<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoUiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function crearProductoConVariante(): Producto
    {
        $categoria = Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos']);

        $producto = Producto::create([
            'nombre' => 'Cuaderno 100 hojas',
            'slug' => 'cuaderno-100-hojas',
            'categoria_id' => $categoria->id,
            'estado' => true,
        ]);

        ProductoVariante::create([
            'producto_id' => $producto->id,
            'codigo_interno' => 'CUA-001',
            'codigo_barras' => '7501234567890',
            'atributos' => ['color' => 'rojo', 'medida' => '1 metro'],
            'costo_real' => 5.00,
            'precio_venta_unidad' => 6.50,
            'precio_venta_docena' => 6.00,
            'precio_venta_mayor' => 5.50,
            'estado' => true,
        ]);

        return $producto;
    }

    public function test_admin_producto_edit_form_shows_url_preview_instead_of_file_upload(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $producto = $this->crearProductoConVariante();

        $this->actingAs($admin)
            ->get("/admin/productos/{$producto->id}/edit")
            ->assertSuccessful()
            ->assertSee('Vista previa')
            ->assertSee('Imagen (URL)');
    }

    public function test_pos_producto_infolist_groups_data_and_hides_costo_real(): void
    {
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);
        $producto = $this->crearProductoConVariante();

        $response = $this->actingAs($vendedor)
            ->get("/pos/productos/{$producto->id}")
            ->assertSuccessful();

        $response->assertSee('Variantes')
            ->assertSee('Color: rojo')
            ->assertSee('Medida: 1 metro')
            ->assertDontSee('5.00');
    }

    public function test_pos_producto_index_table_renders(): void
    {
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);
        $this->crearProductoConVariante();

        $this->actingAs($vendedor)
            ->get('/pos/productos')
            ->assertSuccessful()
            ->assertSee('Cuaderno 100 hojas')
            ->assertSee('Cuadernos');
    }
}
