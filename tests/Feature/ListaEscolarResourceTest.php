<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Categoria;
use App\Models\ListaEscolar;
use App\Models\ListaEscolarDetalle;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaEscolarResourceTest extends TestCase
{
    use RefreshDatabase;

    private function crearVariante(string $codigo, string $precioUnidad): ProductoVariante
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
            'precio_venta_docena' => $precioUnidad,
            'precio_venta_mayor' => $precioUnidad,
            'estado' => true,
        ]);
    }

    public function test_un_rol_no_admin_no_puede_acceder_a_listas_escolares(): void
    {
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);

        $this->actingAs($vendedor)
            ->get('/admin/listas-escolares')
            ->assertForbidden();
    }

    public function test_el_admin_puede_acceder_a_listas_escolares(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/admin/listas-escolares')
            ->assertSuccessful();
    }

    public function test_agregar_un_detalle_recalcula_el_precio_total_estimado(): void
    {
        $lista = ListaEscolar::create(['nombre_plantilla' => '1ro Básico - San Calixto']);
        $this->assertSame('0.00', (string) $lista->fresh()->precio_total_estimado);

        $cuaderno = $this->crearVariante('CUA-001', '6.50');
        $lapiz = $this->crearVariante('LAP-001', '1.20');

        ListaEscolarDetalle::create([
            'lista_escolar_id' => $lista->id,
            'producto_variante_id' => $cuaderno->id,
            'cantidad' => 3,
        ]);

        $this->assertSame('19.50', (string) $lista->fresh()->precio_total_estimado, '3 × 6.50 = 19.50.');

        $detalleLapiz = ListaEscolarDetalle::create([
            'lista_escolar_id' => $lista->id,
            'producto_variante_id' => $lapiz->id,
            'cantidad' => 5,
        ]);

        $this->assertSame('25.50', (string) $lista->fresh()->precio_total_estimado, '19.50 + (5 × 1.20 = 6.00) = 25.50.');

        $detalleLapiz->delete();

        $this->assertSame('19.50', (string) $lista->fresh()->precio_total_estimado, 'Al borrar una línea, el total vuelve a bajar.');
    }
}
