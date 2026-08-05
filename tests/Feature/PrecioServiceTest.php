<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TipoPrecioAplicado;
use App\Models\Categoria;
use App\Models\ConfiguracionNegocio;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Services\PrecioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PrecioServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductoVariante $variante;

    protected function setUp(): void
    {
        parent::setUp();

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
            'precio_venta_unidad' => 10.00,
            'precio_venta_docena' => 9.00,
            'precio_venta_mayor' => 8.00,
            'estado' => true,
        ]);
    }

    public function test_usa_el_umbral_mayor_configurado_en_configuracion_negocio(): void
    {
        $servicio = new PrecioService;

        $calculado = $servicio->calcularPrecio($this->variante->id, 24);

        $this->assertSame(TipoPrecioAplicado::Mayor, $calculado->tipo);
    }

    public function test_al_cambiar_el_umbral_mayor_el_service_refleja_el_nuevo_valor_sin_reiniciar(): void
    {
        ConfiguracionNegocio::actual()->update(['umbral_mayor' => 40]);

        $servicio = new PrecioService;

        $calculado = $servicio->calcularPrecio($this->variante->id, 24);

        $this->assertSame(TipoPrecioAplicado::Docena, $calculado->tipo);
    }

    public function test_lanza_excepcion_si_el_umbral_mayor_configurado_no_supera_el_umbral_de_docena(): void
    {
        ConfiguracionNegocio::actual()->update(['umbral_mayor' => 10]);

        $servicio = new PrecioService;

        $this->expectException(RuntimeException::class);

        $servicio->calcularPrecio($this->variante->id, 24);
    }
}
