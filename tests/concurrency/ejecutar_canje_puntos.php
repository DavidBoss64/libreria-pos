<?php

declare(strict_types=1);
use App\Actions\Puntos\RegistrarMovimientoPuntosAction;
use App\Enums\TipoMovimientoPuntos;
use App\Exceptions\PuntosInsuficientesException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/**
 * Punto de entrada para el test de concurrencia real (@group concurrency).
 *
 * Se invoca como un proceso de PHP independiente (no como parte de la suite de
 * PHPUnit) para poder lanzar dos canjes de puntos GENUINAMENTE simultáneos
 * contra la misma fila de `clientes` en Postgres real, algo que un único
 * proceso PHP de un solo hilo no puede simular ejecutando el Action dos veces
 * en secuencia.
 *
 * Uso: php ejecutar_canje_puntos.php <clienteId> <puntos>
 * Imprime "OK" (exit 0) si el canje se registró, o "INSUFICIENTE" (exit 2)
 * si lockForUpdate() sirvió para detectar saldo insuficiente tras la serialización.
 */

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

[$clienteId, $puntos] = array_map(
    static fn (string $arg): int => (int) $arg,
    array_slice($argv, 1, 2)
);

try {
    (new RegistrarMovimientoPuntosAction)->handle(
        clienteId: $clienteId,
        tipo: TipoMovimientoPuntos::Canjeado,
        puntos: $puntos,
    );
    fwrite(STDOUT, "OK\n");
    exit(0);
} catch (PuntosInsuficientesException) {
    fwrite(STDOUT, "INSUFICIENTE\n");
    exit(2);
}
