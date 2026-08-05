<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\ConfiguracionNegocioPage;
use App\Models\ConfiguracionNegocio;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguracionNegocioPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_rol_no_admin_no_puede_acceder_a_la_pagina(): void
    {
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);

        $this->actingAs($vendedor)
            ->get(ConfiguracionNegocioPage::getUrl())
            ->assertForbidden();
    }

    public function test_el_admin_puede_acceder_a_la_pagina(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(ConfiguracionNegocioPage::getUrl())
            ->assertSuccessful();
    }

    public function test_la_pagina_carga_los_valores_actuales_en_el_formulario(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ConfiguracionNegocioPage::class)
            ->assertSchemaStateSet([
                'umbral_mayor' => 24,
                'soles_por_punto' => 30,
                'valor_por_punto' => '0.30',
            ]);
    }

    public function test_el_admin_puede_guardar_cambios_y_se_reflejan_en_configuracionnegocio_actual(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ConfiguracionNegocioPage::class)
            ->fillForm([
                'umbral_mayor' => 36,
                'soles_por_punto' => 20,
                'valor_por_punto' => 0.50,
            ])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('configuracion_negocio', [
            'id' => 1,
            'umbral_mayor' => 36,
            'soles_por_punto' => 20,
            'valor_por_punto' => 0.50,
        ]);

        $this->assertSame(36, ConfiguracionNegocio::actual()->umbral_mayor);
    }

    public function test_el_umbral_mayor_no_puede_ser_menor_o_igual_al_umbral_de_docena(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ConfiguracionNegocioPage::class)
            ->fillForm([
                'umbral_mayor' => 12,
                'soles_por_punto' => 30,
                'valor_por_punto' => 0.30,
            ])
            ->call('guardar')
            ->assertHasFormErrors(['umbral_mayor']);
    }
}
