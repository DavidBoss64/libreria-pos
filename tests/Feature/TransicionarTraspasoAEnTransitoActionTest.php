<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Traspasos\TransicionarTraspasoAEnTransitoAction;
use App\Enums\TraspasoEstado;
use App\Enums\UserRole;
use App\Exceptions\PreparacionIncompletaException;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\Traspaso;
use App\Models\TraspasoDetalle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TransicionarTraspasoAEnTransitoActionTest extends TestCase
{
    use RefreshDatabase;

    private function crearAlmacen(string $nombre): Almacen
    {
        $sucursal = Sucursal::create(['nombre' => "Sucursal {$nombre}", 'estado' => true]);

        return Almacen::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => $nombre,
            'tipo' => 'deposito',
            'estado' => true,
        ]);
    }

    private function crearVariante(): ProductoVariante
    {
        $categoria = Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos']);

        $producto = Producto::create([
            'nombre' => 'Cuaderno 100 hojas',
            'slug' => 'cuaderno-100-hojas',
            'categoria_id' => $categoria->id,
            'estado' => true,
        ]);

        return ProductoVariante::create([
            'producto_id' => $producto->id,
            'codigo_interno' => 'CUA-001',
            'costo_real' => 5.00,
            'precio_venta_unidad' => 6.50,
            'precio_venta_docena' => 6.00,
            'precio_venta_mayor' => 5.50,
            'estado' => true,
        ]);
    }

    private function crearTraspasoPreparando(): Traspaso
    {
        $origen = $this->crearAlmacen('Central');
        $destino = $this->crearAlmacen('Norte');
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);

        return Traspaso::create([
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'estado' => TraspasoEstado::Preparando,
            'usuario_solicitante_id' => $vendedor->id,
        ]);
    }

    public function test_bloquea_transicion_si_alguna_linea_no_tiene_cantidad_preparada(): void
    {
        $traspaso = $this->crearTraspasoPreparando();
        $variante = $this->crearVariante();

        TraspasoDetalle::create([
            'traspaso_id' => $traspaso->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 10,
            'cantidad_preparada' => null,
        ]);

        $this->expectException(PreparacionIncompletaException::class);

        try {
            (new TransicionarTraspasoAEnTransitoAction())->handle($traspaso);
        } finally {
            $this->assertSame(TraspasoEstado::Preparando, $traspaso->fresh()->estado, 'No debe avanzar de estado si falta preparación.');
        }
    }

    public function test_permite_transicion_si_todas_las_lineas_tienen_cantidad_preparada_incluido_cero(): void
    {
        $traspaso = $this->crearTraspasoPreparando();
        $varianteA = $this->crearVariante();

        TraspasoDetalle::create([
            'traspaso_id' => $traspaso->id,
            'producto_variante_id' => $varianteA->id,
            'cantidad' => 10,
            'cantidad_preparada' => 0,
        ]);

        $resultado = (new TransicionarTraspasoAEnTransitoAction())->handle($traspaso);

        $this->assertSame(TraspasoEstado::EnTransito, $resultado->estado);
    }

    public function test_no_se_puede_transicionar_un_traspaso_que_no_esta_preparando(): void
    {
        $traspaso = $this->crearTraspasoPreparando();
        $traspaso->update(['estado' => TraspasoEstado::Solicitado]);

        $this->expectException(RuntimeException::class);

        (new TransicionarTraspasoAEnTransitoAction())->handle($traspaso);
    }
}
