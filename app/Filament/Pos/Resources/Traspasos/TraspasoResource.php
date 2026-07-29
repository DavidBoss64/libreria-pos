<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Traspasos;

use App\Filament\Concerns\AislaPorSucursal;
use App\Filament\Pos\Resources\Traspasos\Pages\CreateTraspaso;
use App\Filament\Pos\Resources\Traspasos\Pages\ListTraspasos;
use App\Filament\Pos\Resources\Traspasos\Pages\ViewTraspaso;
use App\Filament\Pos\Resources\Traspasos\Schemas\TraspasoForm;
use App\Filament\Pos\Resources\Traspasos\Schemas\TraspasoInfolist;
use App\Filament\Pos\Resources\Traspasos\Tables\TraspasosTable;
use App\Models\Traspaso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TraspasoResource extends Resource
{
    use AislaPorSucursal;

    protected static ?string $model = Traspaso::class;

    protected static ?string $modelLabel = 'Traspaso';

    protected static ?string $pluralModelLabel = 'Traspasos';

    protected static ?string $navigationLabel = 'Traspasos';

    protected static ?string $slug = 'traspasos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    /**
     * El Vendedor ve los traspasos que llegan a SU sucursal (vía almacen_destino),
     * no los que despacha el Almacenero desde el depósito.
     */
    protected static function scopeQueryToSucursal(Builder $query, ?int $sucursalId): Builder
    {
        return $query->whereHas('almacenDestino', fn ($q) => $q->where('sucursal_id', $sucursalId));
    }

    public static function form(Schema $schema): Schema
    {
        return TraspasoForm::configure($schema);
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
            'create' => CreateTraspaso::route('/create'),
            'view' => ViewTraspaso::route('/{record}'),
        ];
    }
}
