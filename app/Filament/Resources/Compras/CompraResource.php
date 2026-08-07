<?php

namespace App\Filament\Resources\Compras;

use App\Filament\Resources\Compras\Pages\CreateCompra;
use App\Filament\Resources\Compras\Pages\ListCompras;
use App\Filament\Resources\Compras\Pages\ViewCompra;
use App\Filament\Support\CompraForm;
use App\Filament\Support\CompraInfolist;
use App\Filament\Support\CompraTable;
use App\Models\Compra;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    protected static ?string $navigationLabel = 'Compra de Mercadería';

    protected static ?string $slug = 'compras';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    /**
     * Sin restricción de almacén: el admin ve/registra compras para cualquier almacén
     * (decisión confirmada con el propietario, sesión 2026-08-05).
     */
    public static function form(Schema $schema): Schema
    {
        return CompraForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompraInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompraTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompras::route('/'),
            'create' => CreateCompra::route('/create'),
            'view' => ViewCompra::route('/{record}'),
        ];
    }
}
