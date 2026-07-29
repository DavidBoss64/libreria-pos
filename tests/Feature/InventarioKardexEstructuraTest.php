<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoMovimientoInventario;
use App\Enums\TraspasoEstado;
use App\Enums\UserRole;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\Traspaso;
use App\Models\TraspasoDetalle;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioKardexEstructuraTest extends TestCase
{
    use RefreshDatabase;

    private function crearVariante(): ProductoVariante
    {
        $producto = Producto::create([
            'nombre' => 'Cuaderno 100 hojas',
            'slug' => 'cuaderno-100-hojas',
            'categoria_id' => \App\Models\Categoria::create(['nombre' => 'Cuadernos', 'slug' => 'cuadernos'])->id,
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

    private function crearAlmacen(string $nombre, string $tipo = 'deposito'): Almacen
    {
        $sucursal = Sucursal::create(['nombre' => "Sucursal {$nombre}", 'estado' => true]);

        return Almacen::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'estado' => true,
        ]);
    }

    public function test_inventarios_rechaza_fila_duplicada_para_el_mismo_almacen_y_variante(): void
    {
        $almacen = $this->crearAlmacen('Central');
        $variante = $this->crearVariante();

        Inventario::create([
            'almacen_id' => $almacen->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 10,
        ]);

        $this->expectException(QueryException::class);

        Inventario::create([
            'almacen_id' => $almacen->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 5,
        ]);
    }

    public function test_almacen_usuario_rechaza_asignacion_duplicada(): void
    {
        $almacen = $this->crearAlmacen('Central');
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        $almacenero->almacenes()->attach($almacen->id);

        $this->expectException(QueryException::class);

        $almacenero->almacenes()->attach($almacen->id);
    }

    public function test_relacion_hub_and_spoke_entre_almacenero_y_multiples_almacenes(): void
    {
        $almacenCentral = $this->crearAlmacen('Central');
        $almacenNorte = $this->crearAlmacen('Norte');
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        $almacenero->almacenes()->attach([$almacenCentral->id, $almacenNorte->id]);

        $this->assertCount(2, $almacenero->fresh()->almacenes);
        $this->assertCount(1, $almacenCentral->fresh()->usuarios);
    }

    public function test_movimiento_inventario_castea_tipo_movimiento_y_no_expone_updated_at(): void
    {
        $almacen = $this->crearAlmacen('Central');
        $variante = $this->crearVariante();
        $usuario = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $movimiento = MovimientoInventario::create([
            'almacen_id' => $almacen->id,
            'producto_variante_id' => $variante->id,
            'tipo_movimiento' => TipoMovimientoInventario::Ingreso,
            'cantidad' => 20,
            'saldo_despues' => 20,
            'motivo' => 'Compra inicial',
            'usuario_id' => $usuario->id,
        ]);

        $this->assertInstanceOf(TipoMovimientoInventario::class, $movimiento->fresh()->tipo_movimiento);
        $this->assertSame(TipoMovimientoInventario::Ingreso, $movimiento->fresh()->tipo_movimiento);
        $this->assertArrayNotHasKey('updated_at', $movimiento->fresh()->getAttributes());
    }

    public function test_traspaso_con_detalles_y_estado_por_defecto_solicitado(): void
    {
        $almacenOrigen = $this->crearAlmacen('Central');
        $almacenDestino = $this->crearAlmacen('Norte', 'tienda');
        $variante = $this->crearVariante();
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);

        $traspaso = Traspaso::create([
            'almacen_origen_id' => $almacenOrigen->id,
            'almacen_destino_id' => $almacenDestino->id,
            'usuario_solicitante_id' => $vendedor->id,
        ]);

        TraspasoDetalle::create([
            'traspaso_id' => $traspaso->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 3,
        ]);

        $this->assertSame(TraspasoEstado::Solicitado, $traspaso->fresh()->estado);
        $this->assertCount(1, $traspaso->fresh()->detalles);
    }
}
