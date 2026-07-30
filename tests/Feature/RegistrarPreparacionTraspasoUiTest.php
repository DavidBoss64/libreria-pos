<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TraspasoEstado;
use App\Enums\UserRole;
use App\Filament\Almacen\Resources\Traspasos\Pages\ListTraspasos;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\Traspaso;
use App\Models\TraspasoDetalle;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresión del bug de recursión infinita: un TextEntry cuyo ->state() leía
 * Get() de su propio nombre de campo ('producto_preview') causaba un loop
 * infinito al montar la acción "Registrar preparación" (agotaba la memoria
 * de PHP sin dejar registro en storage/logs/laravel.log).
 */
class RegistrarPreparacionTraspasoUiTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscenario(): array
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal A', 'estado' => true]);
        $origen = Almacen::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Central', 'tipo' => 'deposito', 'estado' => true]);
        $destino = Almacen::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Tienda', 'tipo' => 'tienda', 'estado' => true]);

        $categoria = Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos']);
        $producto = Producto::create(['nombre' => 'Cuaderno', 'slug' => 'cuaderno', 'categoria_id' => $categoria->id, 'estado' => true]);
        $variante = ProductoVariante::create([
            'producto_id' => $producto->id,
            'codigo_interno' => 'CUA-001',
            'costo_real' => 5,
            'precio_venta_unidad' => 6.5,
            'precio_venta_docena' => 6,
            'precio_venta_mayor' => 5.5,
            'estado' => true,
        ]);

        Inventario::create([
            'almacen_id' => $origen->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 4,
            'cantidad_comprometida' => 0,
            'stock_minimo' => 5,
        ]);

        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almacenero->almacenes()->attach($origen->id);

        $traspaso = Traspaso::create([
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'estado' => TraspasoEstado::Preparando,
            'usuario_solicitante_id' => $vendedor->id,
        ]);

        $detalle = TraspasoDetalle::create([
            'traspaso_id' => $traspaso->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 10,
        ]);

        $this->actingAs($almacenero);
        Filament::setCurrentPanel('almacen');

        return compact('traspaso', 'detalle', 'almacenero');
    }

    public function test_el_modal_de_registrar_preparacion_abre_sin_error(): void
    {
        ['traspaso' => $traspaso] = $this->crearEscenario();

        Livewire::test(ListTraspasos::class)
            ->mountTableAction('registrarPreparacion', $traspaso)
            ->assertSuccessful();
    }
}
