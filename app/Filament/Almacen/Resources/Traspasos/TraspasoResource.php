<?php

declare(strict_types=1);

namespace App\Filament\Almacen\Resources\Traspasos;

use App\Filament\Almacen\Resources\Traspasos\Pages\ListTraspasos;
use App\Filament\Almacen\Resources\Traspasos\Pages\ViewTraspaso;
use App\Filament\Almacen\Resources\Traspasos\Schemas\TraspasoInfolist;
use App\Filament\Almacen\Resources\Traspasos\Tables\TraspasosTable;
use App\Filament\Concerns\AislaPorAlmacen;
use App\Models\Traspaso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TraspasoResource extends Resource
{
    use AislaPorAlmacen;

    protected static ?string $model = Traspaso::class;

    protected static ?string $modelLabel = 'Traspaso';

    protected static ?string $pluralModelLabel = 'Traspasos';

    protected static ?string $navigationLabel = 'Traspasos';

    protected static ?string $slug = 'traspasos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    /**
     * El Almacenero gestiona lo que despacha desde SUS almacenes, no lo que recibe
     * (el destino puede ser cualquier sucursal, modelo hub-and-spoke).
     */
    protected static function scopeQueryToAlmacenes(Builder $query, array $almacenIds): Builder
    {
        return $query->whereIn('almacen_origen_id', $almacenIds);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TraspasoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TraspasosTable::configure($table);
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
            'index' => ListTraspasos::route('/'),
            'view' => ViewTraspaso::route('/{record}'),
        ];
    }
}
