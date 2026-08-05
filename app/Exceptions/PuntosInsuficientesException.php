<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PuntosInsuficientesException extends RuntimeException
{
    public function __construct(int $clienteId, int $disponible, int $solicitado)
    {
        parent::__construct(
            "Puntos insuficientes para el cliente #{$clienteId}: disponible {$disponible}, solicitado {$solicitado}."
        );
    }
}
