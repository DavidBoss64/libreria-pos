<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Almacen\Widgets\MiContextoWidget as AlmacenMiContextoWidget;
use App\Filament\Pos\Widgets\MiContextoWidget as PosMiContextoWidget;
use App\Models\Almacen;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MiContextoWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_pos_muestra_la_sucursal_y_el_rol_del_usuario(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal Norte', 'estado' => true]);
        $vendedor = User::factory()->create([
            'role' => UserRole::Vendedor,
            'is_active' => true,
            'sucursal_id' => $sucursal->id,
        ]);

        Livewire::actingAs($vendedor)
            ->test(PosMiContextoWidget::class)
            ->assertSee('Sucursal Norte')
            ->assertSee('Vendedor');
    }

    public function test_widget_pos_avisa_si_el_usuario_no_tiene_sucursal(): void
    {
        $cajero = User::factory()->create([
            'role' => UserRole::Cajero,
            'is_active' => true,
            'sucursal_id' => null,
        ]);

        Livewire::actingAs($cajero)
            ->test(PosMiContextoWidget::class)
            ->assertSee('Sin sucursal asignada');
    }

    public function test_widget_almacen_muestra_los_almacenes_asignados(): void
    {
        $sucursal = Sucursal::create(['nombre' => 'Sucursal Norte', 'estado' => true]);
        $deposito = Almacen::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Depósito Central',
            'tipo' => 'deposito',
            'estado' => true,
        ]);

        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);
        $almacenero->almacenes()->attach($deposito->id);

        Livewire::actingAs($almacenero)
            ->test(AlmacenMiContextoWidget::class)
            ->assertSee('Depósito Central');
    }

    public function test_widget_almacen_avisa_si_el_usuario_no_tiene_almacenes_asignados(): void
    {
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        Livewire::actingAs($almacenero)
            ->test(AlmacenMiContextoWidget::class)
            ->assertSee('Sin almacenes asignados');
    }
}
