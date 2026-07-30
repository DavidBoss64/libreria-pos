<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Actions\Traspasos\CompletarTraspasoAction;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TraspasoEstado;
use App\Enums\UserRole;
use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\Traspaso;
use App\Models\TraspasoDetalle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CompletarTraspasoActionTest extends TestCase
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

    public function test_completar_traspaso_mueve_stock_de_origen_a_destino(): void
    {
        $origen = $this->crearAlmacen('Central');
        $destino = $this->crearAlmacen('Norte');
        $variante = $this->crearVariante();
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        (new RegistrarMovimientoInventarioAction())->handle(
            $origen->id, $variante->id, TipoMovimientoInventario::Ingreso, 15, 'Compra inicial', $almacenero->id
        );

        $traspaso = Traspaso::create([
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'estado' => TraspasoEstado::EnTransito,
            'usuario_solicitante_id' => $vendedor->id,
        ]);

        TraspasoDetalle::create([
            'traspaso_id' => $traspaso->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 6,
        ]);

        $resultado = (new CompletarTraspasoAction())->handle($traspaso, $almacenero->id);

        $this->assertSame(TraspasoEstado::Completado, $resultado->estado);
        $this->assertSame($almacenero->id, $resultado->usuario_procesador_id);

        $stockOrigen = Inventario::where('almacen_id', $origen->id)->where('producto_variante_id', $variante->id)->first();
        $stockDestino = Inventario::where('almacen_id', $destino->id)->where('producto_variante_id', $variante->id)->first();

        $this->assertSame(9, $stockOrigen->cantidad);
        $this->assertSame(6, $stockDestino->cantidad);
    }

    public function test_completar_traspaso_con_stock_insuficiente_en_origen_revierte_todo(): void
    {
        $origen = $this->crearAlmacen('Central');
        $destino = $this->crearAlmacen('Norte');
        $variante = $this->crearVariante();
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        (new RegistrarMovimientoInventarioAction())->handle(
            $origen->id, $variante->id, TipoMovimientoInventario::Ingreso, 3, 'Compra inicial', $almacenero->id
        );

        $traspaso = Traspaso::create([
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'estado' => TraspasoEstado::EnTransito,
            'usuario_solicitante_id' => $vendedor->id,
        ]);

        TraspasoDetalle::create([
            'traspaso_id' => $traspaso->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 10,
        ]);

        $this->expectException(StockInsuficienteException::class);

        try {
            (new CompletarTraspasoAction())->handle($traspaso, $almacenero->id);
        } finally {
            $this->assertSame(TraspasoEstado::EnTransito, $traspaso->fresh()->estado, 'El traspaso no debe quedar completado si falló.');

            $stockOrigen = Inventario::where('almacen_id', $origen->id)->where('producto_variante_id', $variante->id)->first();
            $this->assertSame(3, $stockOrigen->cantidad, 'El stock de origen no debe descontarse si el traspaso falla.');

            $stockDestino = Inventario::where('almacen_id', $destino->id)->where('producto_variante_id', $variante->id)->first();
            $this->assertNull($stockDestino, 'No debe haberse creado inventario en destino si el traspaso falla.');
        }
    }

    public function test_completar_traspaso_mueve_stock_segun_cantidad_preparada_no_segun_cantidad_solicitada(): void
    {
        $origen = $this->crearAlmacen('Central');
        $destino = $this->crearAlmacen('Norte');
        $variante = $this->crearVariante();
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        (new RegistrarMovimientoInventarioAction())->handle(
            $origen->id, $variante->id, TipoMovimientoInventario::Ingreso, 15, 'Compra inicial', $almacenero->id
        );

        $traspaso = Traspaso::create([
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'estado' => TraspasoEstado::EnTransito,
            'usuario_solicitante_id' => $vendedor->id,
        ]);

        TraspasoDetalle::create([
            'traspaso_id' => $traspaso->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 10,
            'cantidad_preparada' => 6,
        ]);

        (new CompletarTraspasoAction())->handle($traspaso, $almacenero->id);

        $stockOrigen = Inventario::where('almacen_id', $origen->id)->where('producto_variante_id', $variante->id)->first();
        $stockDestino = Inventario::where('almacen_id', $destino->id)->where('producto_variante_id', $variante->id)->first();

        $this->assertSame(9, $stockOrigen->cantidad, 'Debe mover solo lo preparado (6), no lo solicitado (10).');
        $this->assertSame(6, $stockDestino->cantidad);
    }

    public function test_completar_traspaso_con_cantidad_preparada_en_cero_no_genera_movimiento(): void
    {
        $origen = $this->crearAlmacen('Central');
        $destino = $this->crearAlmacen('Norte');
        $variante = $this->crearVariante();
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        (new RegistrarMovimientoInventarioAction())->handle(
            $origen->id, $variante->id, TipoMovimientoInventario::Ingreso, 5, 'Compra inicial', $almacenero->id
        );

        $traspaso = Traspaso::create([
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'estado' => TraspasoEstado::EnTransito,
            'usuario_solicitante_id' => $vendedor->id,
        ]);

        TraspasoDetalle::create([
            'traspaso_id' => $traspaso->id,
            'producto_variante_id' => $variante->id,
            'cantidad' => 10,
            'cantidad_preparada' => 0,
        ]);

        $resultado = (new CompletarTraspasoAction())->handle($traspaso, $almacenero->id);

        $this->assertSame(TraspasoEstado::Completado, $resultado->estado);

        $stockOrigen = Inventario::where('almacen_id', $origen->id)->where('producto_variante_id', $variante->id)->first();
        $stockDestino = Inventario::where('almacen_id', $destino->id)->where('producto_variante_id', $variante->id)->first();

        $this->assertSame(5, $stockOrigen->cantidad, 'No debe descontarse nada si se preparó 0.');
        $this->assertNull($stockDestino, 'No debe crearse inventario en destino si se preparó 0.');
    }

    public function test_no_se_puede_completar_un_traspaso_que_no_esta_en_transito(): void
    {
        $origen = $this->crearAlmacen('Central');
        $destino = $this->crearAlmacen('Norte');
        $vendedor = User::factory()->create(['role' => UserRole::Vendedor, 'is_active' => true]);
        $almacenero = User::factory()->create(['role' => UserRole::Almacenero, 'is_active' => true]);

        $traspaso = Traspaso::create([
            'almacen_origen_id' => $origen->id,
            'almacen_destino_id' => $destino->id,
            'estado' => TraspasoEstado::Solicitado,
            'usuario_solicitante_id' => $vendedor->id,
        ]);

        $this->expectException(RuntimeException::class);

        (new CompletarTraspasoAction())->handle($traspaso, $almacenero->id);
    }
}
