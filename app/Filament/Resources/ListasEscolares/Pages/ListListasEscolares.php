<?php

namespace App\Filament\Resources\ListasEscolares\Pages;

use App\Filament\Resources\ListasEscolares\ListaEscolarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListListasEscolares extends ListRecords
{
    protected static string $resource = ListaEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
