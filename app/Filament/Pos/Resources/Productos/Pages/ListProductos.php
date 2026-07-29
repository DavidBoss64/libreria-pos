<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Productos\Pages;

use App\Filament\Pos\Resources\Productos\ProductoResource;
use Filament\Resources\Pages\ListRecords;

class ListProductos extends ListRecords
{
    protected static string $resource = ProductoResource::class;
}
