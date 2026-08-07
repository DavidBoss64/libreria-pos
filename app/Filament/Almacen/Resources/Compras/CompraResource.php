<?php

namespace App\Filament\Almacen\Resources\Compras;

use App\Filament\Almacen\Resources\Compras\Pages\CreateCompra;
use App\Filament\Almacen\Resources\Compras\Pages\ListCompras;
use App\Filament\Almacen\Resources\Compras\Pages\ViewCompra;
use App\Filament\Concerns\AislaPorAlmacen;
use App\Filament\Support\CompraForm;
use App\Filament\Support\CompraInfolist;
use App\Filament\Support\CompraTable;
use App\Models\Compra;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CompraResource extends Resource
{
    // `Compra` tiene `almacen_id` como columna directa — no hace falta sobreescribir
    // scopeQueryToAlmacenes() (mismo criterio que `InventarioResource`).
    use AislaPorAlmacen;

    protected static ?string $model = Compra::class;

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    protected static ?string $navigationLabel = 'Compra de Mercadería';

    protected static ?string $slug = 'compras';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    /**
     * Restringido a los almacenes asignados al Almacenero (`almacen_usuario`) — decisión
     * confirmada con el propietario, sesión 2026-08-05.
     */
    public static function form(Schema $schema): Schema
    {
        $almacenes = Filament::auth()->user()->almacenes()->with('sucursal')->get();

        return CompraForm::configure($schema, $almacenes);
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
