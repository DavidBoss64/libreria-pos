<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Ventas\CrearPreventaAction;
use App\Enums\UserRole;
use App\Enums\VentaEstado;
use App\Enums\VentaOrigen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CrearPreventaActionTest extends TestCase
{
    use RefreshDatabase;

    private function crearSucursal(string $nombre): Sucursal
    {
        // SucursalObserver (Paso 3.5) crea automáticamente el almacén 'tienda'.
        return Sucursal::create(['nombre' => "Sucursal {$nombre}", 'estado' => true]);
    }

    private function crearVariante(string $codigo = 'CUA-001', float $precioUnidad = 6.50): ProductoVariante
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
            'precio_venta_docena' => $precioUnidad - 0.50,
            'precio_venta_mayor' => $precioUnidad - 1.00,
            'estado' => true,
        ]);
    }

    public function test_crea_preventa_pendiente_con_totales_y_comprometido_correctos(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante();
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);

        $venta = (new CrearPreventaAction())->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Juan Polera Roja',
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 2],
            ],
        ]);

        $this->assertSame(VentaEstado::Pendiente, $venta->estado);
        $this->assertSame(VentaOrigen::Pos, $venta->origen);
        $this->assertStringStartsWith('V-', $venta->numero_ticket);
        $this->assertSame('13.00', (string) $venta->total);
        $this->assertCount(1, $venta->detalles);
        $this->assertNotNull($venta->expira_en);
        $this->assertTrue($venta->expira_en->isFuture());

        $almacenTienda = $sucursal->almacenTienda();
        $inventario = Inventario::where('almacen_id', $almacenTienda->id)
            ->where('producto_variante_id', $variante->id)
            ->first();

        $this->assertSame(2, $inventario->cantidad_comprometida, 'Debe reservar el indicador comprometido sin tocar cantidad real.');
        $this->assertSame(0, $inventario->cantidad, 'La pre-venta no debe descontar stock real.');
    }

    public function test_falla_sin_al_menos_un_detalle(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);

        $this->expectException(InvalidArgumentException::class);

        (new CrearPreventaAction())->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Juan',
            'detalles' => [],
        ]);
    }

    public function test_falla_sin_cliente_registrado_ni_cliente_temporal(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante();
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);

        $this->expectException(InvalidArgumentException::class);

        (new CrearPreventaAction())->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 1],
            ],
        ]);
    }
}
