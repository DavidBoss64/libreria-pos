<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Productos;

use App\Filament\Pos\Resources\Productos\Pages\ListProductos;
use App\Filament\Pos\Resources\Productos\Pages\ViewProducto;
use App\Filament\Pos\Resources\Productos\Schemas\ProductoInfolist;
use App\Filament\Pos\Resources\Productos\Tables\ProductosTable;
use App\Models\Producto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?string $navigationLabel = 'Catálogo';

    protected static ?string $slug = 'productos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function infolist(Schema $schema): Schema
    {
        return ProductoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * El catálogo de solo lectura del Vendedor/Cajero no debe mostrar
     * productos descontinuados (`estado = false`) mezclados con los activos.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('estado', true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductos::route('/'),
            'view' => ViewProducto::route('/{record}'),
        ];
    }
}
