<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Ventas\CrearPreventaAction;
use App\Enums\UserRole;
use App\Enums\VentaEstado;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelarPreventasVencidasCommandTest extends TestCase
{
    use RefreshDatabase;

    private function crearSucursal(string $nombre): Sucursal
    {
        return Sucursal::create(['nombre' => "Sucursal {$nombre}", 'estado' => true]);
    }

    private function crearVariante(string $codigo): ProductoVariante
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
            'precio_venta_unidad' => 6.50,
            'precio_venta_docena' => 6.00,
            'precio_venta_mayor' => 5.50,
            'estado' => true,
        ]);
    }

    public function test_cancela_solo_las_preventas_vencidas_y_libera_su_comprometido(): void
    {
        $sucursal = $this->crearSucursal('Central');
        $varianteVencida = $this->crearVariante('CUA-001');
        $varianteVigente = $this->crearVariante('CUA-002');
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'sucursal_id' => $sucursal->id, 'is_active' => true]);

        $ventaVencida = (new CrearPreventaAction())->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Cliente Vencido',
            'detalles' => [['producto_variante_id' => $varianteVencida->id, 'cantidad' => 2]],
        ]);
        $ventaVencida->forceFill(['expira_en' => now()->subMinutes(5)])->save();

        $ventaVigente = (new CrearPreventaAction())->handle([
            'sucursal_id' => $sucursal->id,
            'vendedor_id' => $vendedor->id,
            'cliente_temporal' => 'Cliente Vigente',
            'detalles' => [['producto_variante_id' => $varianteVigente->id, 'cantidad' => 1]],
        ]);

        $this->artisan('ventas:cancelar-preventas-vencidas')->assertSuccessful();

        $this->assertSame(VentaEstado::Anulado, $ventaVencida->fresh()->estado);
        $this->assertSame(VentaEstado::Pendiente, $ventaVigente->fresh()->estado, 'No debe tocar pre-ventas que aún no expiran.');

        $almacenTienda = $sucursal->almacenTienda();

        $inventarioVencida = Inventario::where('almacen_id', $almacenTienda->id)->where('producto_variante_id', $varianteVencida->id)->first();
        $this->assertSame(0, $inventarioVencida->cantidad_comprometida);

        $inventarioVigente = Inventario::where('almacen_id', $almacenTienda->id)->where('producto_variante_id', $varianteVigente->id)->first();
        $this->assertSame(1, $inventarioVigente->cantidad_comprometida, 'La pre-venta vigente conserva su comprometido.');
    }
}
