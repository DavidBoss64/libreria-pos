<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Traspasos\Pages;

use App\Filament\Pos\Resources\Traspasos\TraspasoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTraspasos extends ListRecords
{
    protected static string $resource = TraspasoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
