<?php

namespace App\Filament\Resources\ListasEscolares\Pages;

use App\Filament\Resources\ListasEscolares\ListaEscolarResource;
use App\Filament\Support\AccionesPapelera;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditListaEscolar extends EditRecord
{
    protected static string $resource = ListaEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            AccionesPapelera::forceDeleteSeguro(),
            RestoreAction::make(),
        ];
    }
}
