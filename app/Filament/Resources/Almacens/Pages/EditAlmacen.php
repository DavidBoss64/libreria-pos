<?php

namespace App\Filament\Resources\Almacens\Pages;

use App\Filament\Resources\Almacens\AlmacenResource;
use App\Filament\Support\AccionesPapelera;
use App\Models\Almacen;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAlmacen extends EditRecord
{
    protected static string $resource = AlmacenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AccionesPapelera::delete(
                fn (Almacen $almacen) => $almacen->tieneStockFisico(),
                'Este almacén todavía tiene stock físico registrado (cantidad mayor a cero) en al menos un producto. Traspasa o ajusta ese stock a cero antes de enviarlo a la papelera.',
            ),
            AccionesPapelera::forceDeleteSeguro(),
            RestoreAction::make(),
        ];
    }
}
