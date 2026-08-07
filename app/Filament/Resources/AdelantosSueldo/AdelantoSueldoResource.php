<?php

namespace App\Filament\Resources\AdelantosSueldo;

use App\Filament\Resources\AdelantosSueldo\Pages\CreateAdelantoSueldo;
use App\Filament\Resources\AdelantosSueldo\Pages\EditAdelantoSueldo;
use App\Filament\Resources\AdelantosSueldo\Pages\ListAdelantosSueldo;
use App\Filament\Resources\AdelantosSueldo\Schemas\AdelantoSueldoForm;
use App\Filament\Resources\AdelantosSueldo\Tables\AdelantosSueldoTable;
use App\Models\AdelantoSueldo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;


class AdelantoSueldoResource extends Resource
{
    protected static ?string $model = AdelantoSueldo::class;

    protected static ?string $modelLabel = 'Adelanto de Sueldo';

    protected static ?string $pluralModelLabel = 'Adelantos de Sueldo';

    protected static ?string $navigationLabel = 'Adelantos de Sueldo';

    protected static ?string $slug = 'adelantos-sueldo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function form(Schema $schema): Schema
    {
        return AdelantoSueldoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdelantosSueldoTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdelantosSueldo::route('/'),
            'create' => CreateAdelantoSueldo::route('/create'),
            'edit' => EditAdelantoSueldo::route('/{record}/edit'),
        ];
    }
}
