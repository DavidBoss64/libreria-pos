<?php

declare(strict_types=1);

namespace App\Filament\Resources\Almacens\Schemas;

use App\Enums\AlmacenTipo;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AlmacenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sucursal_id')
                    ->label('Sucursal')
                    ->relationship('sucursal', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(150),
                Select::make('tipo')
                    ->options(AlmacenTipo::class)
                    ->required()
                    ->helperText('Tienda: punto de venta, aquí venden tus vendedores. Depósito: bodega central, no se vende directo aquí.'),
                Toggle::make('estado')
                    ->default(true)
                    ->required(),
            ]);
    }
}
