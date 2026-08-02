<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Support\AccionAjusteInventario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class AjusteInventario extends Page
{
    protected string $view = 'filament.pages.ajuste-inventario';

    protected static ?string $title = 'Ajuste de Inventario';

    protected static ?string $navigationLabel = 'Ajuste de Inventario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            AccionAjusteInventario::make(),
        ];
    }
}
