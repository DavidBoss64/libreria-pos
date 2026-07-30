<?php

namespace App\Filament\Resources\Sucursals\Pages;

use App\Filament\Resources\Sucursals\SucursalResource;
use App\Filament\Support\AccionesPapelera;
use App\Models\Sucursal;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSucursal extends EditRecord
{
    protected static string $resource = SucursalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AccionesPapelera::delete(
                fn (Sucursal $sucursal) => $sucursal->tieneStockFisico(),
                'Esta sucursal tiene stock físico registrado en al menos uno de sus almacenes. No puede enviarse a la papelera mientras exista mercadería activa allí.',
            ),
            AccionesPapelera::forceDeleteSeguro(),
            RestoreAction::make(),
        ];
    }
}
