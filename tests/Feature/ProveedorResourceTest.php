<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProveedorResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_rol_no_admin_no_puede_acceder_a_proveedores(): void
    {
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        $this->actingAs($almacenero)
            ->get('/admin/proveedores')
            ->assertForbidden();
    }

    public function test_el_admin_puede_acceder_a_proveedores(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/admin/proveedores')
            ->assertSuccessful();
    }
}
