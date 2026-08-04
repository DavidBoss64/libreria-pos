<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Ventas;

use App\Filament\Concerns\AislaPorSucursal;
use App\Filament\Pos\Resources\Ventas\Pages\CreateVenta;
use App\Filament\Pos\Resources\Ventas\Pages\ListVentas;
use App\Filament\Pos\Resources\Ventas\Pages\ViewVenta;
use App\Filament\Pos\Resources\Ventas\Schemas\VentaForm;
use App\Filament\Pos\Resources\Ventas\Schemas\VentaInfolist;
use App\Filament\Pos\Resources\Ventas\Tables\VentasTable;
use App\Models\Venta;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VentaResource extends Resource
{
    // `Venta` tiene `sucursal_id` como columna directa — no hace falta sobreescribir
    // scopeQueryToSucursal() (a diferencia de Inventario/Traspaso, que aíslan vía almacén).
    use AislaPorSucursal;

    protected static ?string $model = Venta::class;

    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?string $slug = 'ventas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    public static function form(Schema $schema): Schema
    {
        return VentaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VentaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VentasTable::configure($table);
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
            'index' => ListVentas::route('/'),
            'create' => CreateVenta::route('/create'),
            'view' => ViewVenta::route('/{record}'),
        ];
    }
}
