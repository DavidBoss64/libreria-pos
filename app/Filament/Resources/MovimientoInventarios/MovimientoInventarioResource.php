<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovimientoInventarios;

use App\Filament\Resources\MovimientoInventarios\Pages\ListMovimientoInventarios;
use App\Filament\Resources\MovimientoInventarios\Tables\MovimientoInventariosTable;
use App\Models\MovimientoInventario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MovimientoInventarioResource extends Resource
{
    protected static ?string $model = MovimientoInventario::class;

    protected static ?string $modelLabel = 'Movimiento de Inventario';

    protected static ?string $pluralModelLabel = 'Movimientos de Inventario';

    protected static ?string $navigationLabel = 'Movimientos de Inventario';

    protected static ?string $slug = 'movimientos-inventario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function table(Table $table): Table
    {
        return MovimientoInventariosTable::configure($table);
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
            'index' => ListMovimientoInventarios::route('/'),
        ];
    }
}
