<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoPrecioAplicado: string
{
    case Unidad = 'unidad';
    case Docena = 'docena';
    case Mayor = 'mayor';
}
