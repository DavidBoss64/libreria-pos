<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdelantoSueldoEstado;
use App\Enums\UserRole;
use App\Filament\Resources\AdelantosSueldo\Pages\CreateAdelantoSueldo;
use App\Filament\Resources\AdelantosSueldo\Pages\ListAdelantosSueldo;
use App\Models\AdelantoSueldo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdelantoSueldoResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_rol_no_admin_no_puede_acceder_a_adelantos_de_sueldo(): void
    {
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);

        $this->actingAs($vendedor)
            ->get('/admin/adelantos-sueldo')
            ->assertForbidden();
    }

    public function test_el_admin_puede_acceder_a_adelantos_de_sueldo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/admin/adelantos-sueldo')
            ->assertSuccessful();
    }

    public function test_el_admin_registra_un_adelanto_y_queda_como_pendiente_de_descuento_a_su_propio_nombre(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $empleado = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateAdelantoSueldo::class)
            ->fillForm([
                'usuario_id' => $empleado->id,
                'monto' => '150.00',
                'fecha_adelanto' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('adelantos_sueldo', [
            'usuario_id' => $empleado->id,
            'registrado_por_id' => $admin->id,
            'estado' => AdelantoSueldoEstado::PendienteDescuento->value,
        ]);
    }

    public function test_un_empleado_inactivo_no_se_puede_seleccionar_como_receptor_del_adelanto(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $inactivo = User::factory()->create(['role' => UserRole::Cajero, 'is_active' => false]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        // Mismo criterio que `almacen_id` en `CompraForm` (ver CompraResourceTest): al no
        // estar entre las opciones válidas del Select, Filament rechaza el envío a nivel
        // de validación del formulario.
        Livewire::test(CreateAdelantoSueldo::class)
            ->fillForm([
                'usuario_id' => $inactivo->id,
                'monto' => '150.00',
                'fecha_adelanto' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasFormErrors(['usuario_id']);

        $this->assertDatabaseMissing('adelantos_sueldo', ['usuario_id' => $inactivo->id]);
    }

    public function test_la_accion_marcar_como_descontado_cambia_el_estado_y_solo_aparece_si_esta_pendiente(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $empleado = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);

        $adelanto = AdelantoSueldo::create([
            'usuario_id' => $empleado->id,
            'registrado_por_id' => $admin->id,
            'monto' => 100,
            'fecha_adelanto' => now()->toDateString(),
            'estado' => AdelantoSueldoEstado::PendienteDescuento,
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListAdelantosSueldo::class)
            ->assertTableActionVisible('marcarDescontado', $adelanto)
            ->callTableAction('marcarDescontado', $adelanto)
            ->assertHasNoTableActionErrors();

        $this->assertSame(AdelantoSueldoEstado::Descontado, $adelanto->fresh()->estado);

        Livewire::test(ListAdelantosSueldo::class)
            ->assertTableActionHidden('marcarDescontado', $adelanto);
    }
}
