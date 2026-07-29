<?php

declare(strict_types=1);

namespace App\Filament\Almacen\Resources\Inventarios;

use App\Filament\Almacen\Resources\Inventarios\Pages\ListInventarios;
use App\Filament\Almacen\Resources\Inventarios\Tables\InventariosTable;
use App\Filament\Concerns\AislaPorAlmacen;
use App\Models\Inventario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventarioResource extends Resource
{
    use AislaPorAlmacen;

    protected static ?string $model = Inventario::class;

    protected static ?string $modelLabel = 'Stock';

    protected static ?string $pluralModelLabel = 'Stock';

    protected static ?string $navigationLabel = 'Stock';

    protected static ?string $slug = 'inventarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    public static function table(Table $table): Table
    {
        return InventariosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventarios::route('/'),
        ];
    }
}
