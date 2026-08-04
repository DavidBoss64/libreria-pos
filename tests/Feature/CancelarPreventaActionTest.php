<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Ventas\CancelarPreventaAction;
use App\Actions\Ventas\CrearPreventaAction;
use App\Enums\UserRole;
use App\Enums\VentaEstado;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CancelarPreventaActionTest extends TestCase
{
    use RefreshDatabase;

    private function crearSucursal(string $nombre): Sucursal
    {
        return Sucursal::create(['nombre' => "Sucursal {$nombre}", 'estado' => true]);
    }

    private function crearVariante(): ProductoVariante
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
            'codigo_interno' => 'CUA-'.uniqid(),
            'costo_real' => 5.00,
            'precio_venta_unidad' => 6.50,
            'precio_venta_docena' => 6.00,
            'precio_venta_mayor' => 5.50,
            'estado' => true,
        ]);
    }

    public function test_cancela_preventa_pendiente_y_libera_el_comprometido(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $variante = $this->crearVariante();
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);

        $venta = (new CrearPreventaAction())->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Juan Polera Roja',
            'detalles' => [
                ['producto_variante_id' => $variante->id, 'cantidad' => 3],
            ],
        ]);

        $resultado = (new CancelarPreventaAction())->handle($venta);

        $this->assertSame(VentaEstado::Anulado, $resultado->estado);

        $almacenTienda = $sucursal->almacenTienda();
        $inventario = Inventario::where('almacen_id', $almacenTienda->id)
            ->where('producto_variante_id', $variante->id)
            ->first();

        $this->assertSame(0, $inventario->cantidad_comprometida, 'Cancelar debe liberar todo lo comprometido.');
    }

    public function test_no_permite_cancelar_una_venta_que_no_esta_pendiente(): void
    {
        $sucursal = $this->crearSucursal('Central');

        $venta = Venta::create([
            'sucursal_id' => $sucursal->id,
            'cliente_temporal' => 'Juan',
            'total' => 10,
            'estado' => VentaEstado::Completado,
        ]);

        $this->expectException(RuntimeException::class);

        (new CancelarPreventaAction())->handle($venta);
    }
}
