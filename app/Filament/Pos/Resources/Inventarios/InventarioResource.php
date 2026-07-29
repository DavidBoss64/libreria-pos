<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Inventarios;

use App\Filament\Concerns\AislaPorSucursal;
use App\Filament\Pos\Resources\Inventarios\Pages\ListInventarios;
use App\Filament\Pos\Resources\Inventarios\Tables\InventariosTable;
use App\Models\Inventario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventarioResource extends Resource
{
    use AislaPorSucursal;

    protected static ?string $model = Inventario::class;

    protected static ?string $modelLabel = 'Stock';

    protected static ?string $pluralModelLabel = 'Stock';

    protected static ?string $navigationLabel = 'Stock';

    protected static ?string $slug = 'inventarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static function scopeQueryToSucursal(Builder $query, ?int $sucursalId): Builder
    {
        return $query->whereHas('almacen', fn ($q) => $q->where('sucursal_id', $sucursalId));
    }

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
