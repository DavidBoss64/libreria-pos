<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PreparacionIncompletaException extends RuntimeException
{
    public function __construct(int $traspasoId)
    {
        parent::__construct(
            "El traspaso {$traspasoId} tiene líneas sin cantidad_preparada registrada. ".
            'Registra la preparación de todas las líneas (aunque sea 0) antes de despachar.'
        );
    }
}
