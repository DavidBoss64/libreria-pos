<?php

declare(strict_types=1);

/**
 * Punto de entrada para el test de concurrencia real (@group concurrency).
 *
 * Se invoca como un proceso de PHP independiente (no como parte de la suite de
 * PHPUnit) para poder lanzar dos operaciones GENUINAMENTE simultáneas contra la
 * misma fila de `inventarios` en Postgres real, algo que un único proceso PHP
 * de un solo hilo no puede simular ejecutando el Action dos veces en secuencia.
 *
 * Uso: php ejecutar_salida_inventario.php <almacenId> <productoVarianteId> <cantidad> <usuarioId>
 * Imprime "OK" (exit 0) si el movimiento se registró, o "INSUFICIENTE" (exit 2)
 * si lockForUpdate() sirvió para detectar stock insuficiente tras la serialización.
 */

require __DIR__.'/../../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

[$almacenId, $productoVarianteId, $cantidad, $usuarioId] = array_map(
    static fn (string $arg): int => (int) $arg,
    array_slice($argv, 1, 4)
);

try {
    (new App\Actions\Inventario\RegistrarMovimientoInventarioAction())->handle(
        almacenId: $almacenId,
        productoVarianteId: $productoVarianteId,
        tipoMovimiento: App\Enums\TipoMovimientoInventario::Salida,
        cantidad: $cantidad,
        motivo: 'Test de concurrencia (@group concurrency)',
        usuarioId: $usuarioId,
    );
    fwrite(STDOUT, "OK\n");
    exit(0);
} catch (App\Exceptions\StockInsuficienteException) {
    fwrite(STDOUT, "INSUFICIENTE\n");
    exit(2);
}
