<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\AjusteInventario;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AjusteInventarioPageTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;

    private ProductoVariante $variante;

    protected function setUp(): void
    {
        parent::setUp();

        $sucursal = Sucursal::create(['nombre' => 'Sucursal Central', 'estado' => true]);

        $this->almacen = Almacen::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Depósito Central',
            'tipo' => 'deposito',
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
    }

    public function test_un_rol_no_admin_no_puede_acceder_a_la_pagina_de_ajuste(): void
    {
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);

        $this->actingAs($vendedor)
            ->get(AjusteInventario::getUrl())
            ->assertForbidden();
    }

    public function test_el_admin_puede_acceder_a_la_pagina_de_ajuste(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(AjusteInventario::getUrl())
            ->assertSuccessful();
    }

    public function test_admin_puede_registrar_un_ajuste_positivo_y_actualiza_el_stock(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(AjusteInventario::class)
            ->callAction('registrarAjuste', data: [
                'almacen_id' => $this->almacen->id,
                'producto_id' => $this->variante->producto_id,
                'producto_variante_id' => $this->variante->id,
                'cantidad' => 15,
                'motivo' => 'Stock inicial (Paso 3.4)',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('movimientos_inventario', [
            'almacen_id' => $this->almacen->id,
            'producto_variante_id' => $this->variante->id,
            'tipo_movimiento' => 'ajuste',
            'cantidad' => 15,
            'saldo_despues' => 15,
            'motivo' => 'Stock inicial (Paso 3.4)',
            'usuario_id' => $admin->id,
        ]);

        $inventario = Inventario::where('almacen_id', $this->almacen->id)
            ->where('producto_variante_id', $this->variante->id)
            ->first();

        $this->assertSame(15, $inventario->cantidad);
    }

    public function test_ajuste_negativo_que_dejaria_stock_en_negativo_se_rechaza_sin_modificar_inventario(): void
    {
        Inventario::create([
            'almacen_id' => $this->almacen->id,
            'producto_variante_id' => $this->variante->id,
            'cantidad' => 5,
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(AjusteInventario::class)
            ->callAction('registrarAjuste', data: [
                'almacen_id' => $this->almacen->id,
                'producto_id' => $this->variante->producto_id,
                'producto_variante_id' => $this->variante->id,
                'cantidad' => -10,
                'motivo' => 'Merma por daño',
            ])
            ->assertActionHalted('registrarAjuste');

        $inventario = Inventario::where('almacen_id', $this->almacen->id)
            ->where('producto_variante_id', $this->variante->id)
            ->first();

        $this->assertSame(5, $inventario->cantidad, 'El stock no debe cambiar cuando el ajuste se rechaza.');
        $this->assertSame(0, MovimientoInventario::where('motivo', 'Merma por daño')->count());
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(AjusteInventario::class)
            ->callAction('registrarAjuste', data: [
                'almacen_id' => $this->almacen->id,
                'producto_id' => $this->variante->producto_id,
                'producto_variante_id' => $this->variante->id,
                'cantidad' => 5,
                'motivo' => '',
            ])
            ->assertHasActionErrors(['motivo' => 'required']);
    }

    public function test_la_cantidad_no_puede_ser_cero(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(AjusteInventario::class)
            ->callAction('registrarAjuste', data: [
                'almacen_id' => $this->almacen->id,
                'producto_id' => $this->variante->producto_id,
                'producto_variante_id' => $this->variante->id,
                'cantidad' => 0,
                'motivo' => 'Motivo cualquiera',
            ])
            ->assertHasActionErrors(['cantidad']);
    }
}
