<?php

declare(strict_types=1);

namespace App\Filament\Resources\Inventarios\Pages;

use App\Filament\Resources\Inventarios\InventarioResource;
use Filament\Resources\Pages\ListRecords;

class ListInventarios extends ListRecords
{
    protected static string $resource = InventarioResource::class;
}
