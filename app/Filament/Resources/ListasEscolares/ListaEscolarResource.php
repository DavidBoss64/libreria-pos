<?php

namespace App\Filament\Resources\ListasEscolares;

use App\Filament\Resources\ListasEscolares\Pages\CreateListaEscolar;
use App\Filament\Resources\ListasEscolares\Pages\EditListaEscolar;
use App\Filament\Resources\ListasEscolares\Pages\ListListasEscolares;
use App\Filament\Resources\ListasEscolares\RelationManagers\DetallesRelationManager;
use App\Filament\Resources\ListasEscolares\Schemas\ListaEscolarForm;
use App\Filament\Resources\ListasEscolares\Tables\ListasEscolaresTable;
use App\Models\ListaEscolar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ListaEscolarResource extends Resource
{
    protected static ?string $model = ListaEscolar::class;

    protected static ?string $modelLabel = 'Lista escolar';

    protected static ?string $pluralModelLabel = 'Listas escolares';

    protected static ?string $navigationLabel = 'Listas escolares';

    protected static ?string $slug = 'listas-escolares';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function form(Schema $schema): Schema
    {
        return ListaEscolarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ListasEscolaresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DetallesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListasEscolares::route('/'),
            'create' => CreateListaEscolar::route('/create'),
            'edit' => EditListaEscolar::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
