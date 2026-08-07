<?php

namespace App\Filament\Resources\AdelantosSueldo\Pages;

use App\Filament\Resources\AdelantosSueldo\AdelantoSueldoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdelantosSueldo extends ListRecords
{
    protected static string $resource = AdelantoSueldoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
