<?php

declare(strict_types=1);

namespace App\Filament\Pos\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class MiContextoWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();

        return [
            Stat::make('Mi sucursal', $user?->sucursal?->nombre ?? 'Sin sucursal asignada')
                ->description($user?->role->getLabel() ?? '—')
                ->descriptionIcon(Heroicon::OutlinedUser)
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->color($user?->sucursal ? 'info' : 'danger'),
        ];
    }
}
