<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
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
class PuntosConcurrenciaTest extends TestCase
{
    private const CONEXION = 'pgsql_concurrencia';

    private int $clienteId;

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

        $this->clienteId = $this->tabla('clientes')->insertGetId([
            'nombres' => 'Cliente',
            'apellidos' => 'Test Concurrencia',
            'puntos_acumulados' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->tabla('movimientos_puntos')->where('cliente_id', $this->clienteId)->delete();
        $this->tabla('clientes')->where('id', $this->clienteId)->delete();

        parent::tearDown();
    }

    private function tabla(string $nombre): Builder
    {
        return DB::connection(self::CONEXION)->table($nombre);
    }

    public function test_dos_canjes_concurrentes_no_pueden_dejar_el_saldo_de_puntos_negativo(): void
    {
        // Saldo inicial: 10 puntos. Dos procesos canjean 7 c/u (14 en total) al mismo
        // tiempo: si lockForUpdate() serializa correctamente, solo UNO debe tener éxito.
        $script = __DIR__.'/../../concurrency/ejecutar_canje_puntos.php';

        $envReal = [
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => env('DB_DATABASE_CONCURRENCY_TEST', 'libreria_pos'),
        ];

        $procesoA = new Process(
            [PHP_BINARY, $script, (string) $this->clienteId, '7'],
            base_path(),
            $envReal,
        );
        $procesoB = new Process(
            [PHP_BINARY, $script, (string) $this->clienteId, '7'],
            base_path(),
            $envReal,
        );

        $procesoA->start();
        $procesoB->start();

        $procesoA->wait();
        $procesoB->wait();

        $salidas = [trim($procesoA->getOutput()), trim($procesoB->getOutput())];
        sort($salidas);

        $this->assertSame(['INSUFICIENTE', 'OK'], $salidas, 'Con lockForUpdate() roto, ambos procesos podrían leer el mismo saldo y "OK" dos veces.');

        $cliente = $this->tabla('clientes')->where('id', $this->clienteId)->first();

        $this->assertSame(3, $cliente->puntos_acumulados, 'El saldo final debe reflejar exactamente UN canje de 7 sobre 10 puntos iniciales.');

        $this->assertSame(
            1,
            $this->tabla('movimientos_puntos')->where('cliente_id', $this->clienteId)->count(),
            'Solo debe existir un registro de Kardex de puntos: el del canje que sí se pudo aplicar.'
        );
    }
}
