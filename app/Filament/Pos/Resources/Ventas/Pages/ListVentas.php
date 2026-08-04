<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Ventas\Pages;

use App\Filament\Pos\Resources\Ventas\VentaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    /**
     * `CreateAction` se oculta solo automáticamente si `VentaPolicy::create()` lo
     * deniega — así el Cajero (que comparte este mismo panel/Resource) no ve el
     * botón "Nueva venta", sin necesitar una pantalla separada.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva pre-venta'),
        ];
    }
}
