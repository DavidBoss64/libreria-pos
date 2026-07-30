<?php

declare(strict_types=1);

namespace App\Filament\Almacen\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class MiContextoWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();
        $almacenes = $user?->almacenes()->pluck('nombre') ?? collect();

        return [
            Stat::make(
                'Mis almacenes',
                $almacenes->isNotEmpty() ? $almacenes->implode(', ') : 'Sin almacenes asignados'
            )
                ->description('Almacenero')
                ->descriptionIcon(Heroicon::OutlinedUser)
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color($almacenes->isNotEmpty() ? 'info' : 'danger'),
        ];
    }
}
