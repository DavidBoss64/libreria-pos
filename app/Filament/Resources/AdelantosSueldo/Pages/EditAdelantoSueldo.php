<?php

namespace App\Filament\Resources\AdelantosSueldo\Pages;

use App\Filament\Resources\AdelantosSueldo\AdelantoSueldoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdelantoSueldo extends EditRecord
{
    protected static string $resource = AdelantoSueldoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
