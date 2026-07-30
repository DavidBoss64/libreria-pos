<?php

namespace App\Filament\Resources\Marcas\Pages;

use App\Filament\Resources\Marcas\MarcaResource;
use App\Filament\Support\AccionesPapelera;
use App\Models\Marca;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMarca extends EditRecord
{
    protected static string $resource = MarcaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AccionesPapelera::delete(
                fn (Marca $marca) => $marca->tieneProductosActivos(),
                'Esta marca tiene productos activos asociados. Reasígnalos a otra marca o quítales la marca primero.',
            ),
            AccionesPapelera::forceDeleteSeguro(),
            RestoreAction::make(),
        ];
    }
}
