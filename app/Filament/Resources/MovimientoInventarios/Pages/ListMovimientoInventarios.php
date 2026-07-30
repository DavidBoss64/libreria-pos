<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovimientoInventarios\Pages;

use App\Filament\Resources\MovimientoInventarios\MovimientoInventarioResource;
use Filament\Resources\Pages\ListRecords;

class ListMovimientoInventarios extends ListRecords
{
    protected static string $resource = MovimientoInventarioResource::class;
}
