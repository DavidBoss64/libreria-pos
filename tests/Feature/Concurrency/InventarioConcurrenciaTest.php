<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Prueba de concurrencia REAL (dos procesos de PHP separados, dos conexiones
 * PDO distintas) contra el Postgres de desarrollo — no la suite sqlite en
 * memoria de siempre, que no soporta lockForUpdate() real. Ver CLAUDE.md
 * regla 17 para cuándo aplica este patrón y cómo correrlo por separado:
 *
 *   php artisan test --group=concurrency
 *
 * Requiere que la base de datos de `.env` (Postgres) esté arriba y migrada.
 */
#[Group('concurrency')]
class InventarioConcurrenciaTest extends TestCase
{
    private const CONEXION = 'pgsql_concurrencia';

    private int $almacenId;

    private int $productoVarianteId;

    private int $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.'.self::CONEXION => [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE_CONCURRENCY_TEST', 'libreria_pos'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => env('DB_SSLMODE', 'prefer'),
            ],
        ]);

        $sucursalId = $this->tabla('sucursales')->insertGetId([
            'nombre' => 'Sucursal Test Concurrencia',
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $almacenId = $this->tabla('almacenes')->insertGetId([
            'sucursal_id' => $sucursalId,
            'nombre' => 'Almacén Test Concurrencia',
            'tipo' => 'deposito',
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoriaId = $this->tabla('categorias')->insertGetId([
            'nombre' => 'Categoría Test Concurrencia',
            'slug' => 'categoria-test-concurrencia-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productoId = $this->tabla('productos')->insertGetId([
            'nombre' => 'Producto Test Concurrencia',
            'slug' => 'producto-test-concurrencia-'.uniqid(),
            'categoria_id' => $categoriaId,
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productoVarianteId = $this->tabla('producto_variantes')->insertGetId([
            'producto_id' => $productoId,
            'codigo_interno' => 'CONC-'.uniqid(),
            'costo_real' => 5.00,
            'precio_venta_unidad' => 6.50,
            'precio_venta_docena' => 6.00,
            'precio_venta_mayor' => 5.50,
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tabla('inventarios')->insert([
            'almacen_id' => $almacenId,
            'producto_variante_id' => $productoVarianteId,
            'cantidad' => 10,
            'cantidad_comprometida' => 0,
            'stock_minimo' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $usuarioId = $this->tabla('users')->insertGetId([
            'nombres' => 'Admin',
            'apellidos' => 'Test Concurrencia',
            'email' => 'admin-concurrencia-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->almacenId = $almacenId;
        $this->productoVarianteId = $productoVarianteId;
        $this->usuarioId = $usuarioId;
    }

    protected function tearDown(): void
    {
        $this->tabla('movimientos_inventario')->where('producto_variante_id', $this->productoVarianteId)->delete();
        $this->tabla('inventarios')->where('producto_variante_id', $this->productoVarianteId)->delete();
        $this->tabla('producto_variantes')->where('id', $this->productoVarianteId)->delete();
        $this->tabla('productos')->where('id', '>', 0)->where('slug', 'like', 'producto-test-concurrencia-%')->delete();
        $this->tabla('categorias')->where('slug', 'like', 'categoria-test-concurrencia-%')->delete();
        $this->tabla('almacenes')->where('id', $this->almacenId)->delete();
        $this->tabla('sucursales')->where('id', '>', 0)->where('nombre', 'Sucursal Test Concurrencia')->delete();
        $this->tabla('users')->where('id', $this->usuarioId)->delete();

        parent::tearDown();
    }

    private function tabla(string $nombre): \Illuminate\Database\Query\Builder
    {
        return DB::connection(self::CONEXION)->table($nombre);
    }

    public function test_dos_salidas_concurrentes_no_pueden_sobrevender_el_stock(): void
    {
        // Stock inicial: 10. Dos procesos piden 7 c/u (14 en total) al mismo tiempo:
        // si lockForUpdate() serializa correctamente, solo UNO debe tener éxito.
        $script = __DIR__.'/../../concurrency/ejecutar_salida_inventario.php';

        $envReal = [
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => env('DB_DATABASE_CONCURRENCY_TEST', 'libreria_pos'),
        ];

        $procesoA = new Process(
            [PHP_BINARY, $script, (string) $this->almacenId, (string) $this->productoVarianteId, '7', (string) $this->usuarioId],
            base_path(),
            $envReal,
        );
        $procesoB = new Process(
            [PHP_BINARY, $script, (string) $this->almacenId, (string) $this->productoVarianteId, '7', (string) $this->usuarioId],
            base_path(),
            $envReal,
        );

        $procesoA->start();
        $procesoB->start();

        $procesoA->wait();
        $procesoB->wait();

        $salidas = [trim($procesoA->getOutput()), trim($procesoB->getOutput())];
        sort($salidas);

        $this->assertSame(['INSUFICIENTE', 'OK'], $salidas, 'Con lockForUpdate() roto, ambos procesos podrían leer el mismo stock y "OK" dos veces.');

        $inventario = $this->tabla('inventarios')
            ->where('almacen_id', $this->almacenId)
            ->where('producto_variante_id', $this->productoVarianteId)
            ->first();

        $this->assertSame(3, $inventario->cantidad, 'El stock final debe reflejar exactamente UNA salida de 7 sobre 10 iniciales.');

        $this->assertSame(
            1,
            $this->tabla('movimientos_inventario')->where('producto_variante_id', $this->productoVarianteId)->count(),
            'Solo debe existir un registro de Kardex: el de la salida que sí se pudo aplicar.'
        );
    }
}
