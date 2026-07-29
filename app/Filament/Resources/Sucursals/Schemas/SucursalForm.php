<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sucursals\Schemas;

use App\Enums\AlmacenTipo;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SucursalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la Sucursal')
                    ->components([
                        TextInput::make('nombre')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('direccion')
                            ->maxLength(255),
                        Toggle::make('estado')
                            ->default(true)
                            ->required(),
                    ]),
                Section::make('Almacenes')
                    ->description('Toda sucursal debe crearse con al menos un almacén (ej. tienda y/o depósito).')
                    ->visibleOn('create')
                    ->components([
                        Repeater::make('almacenes')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('nombre')
                                    ->label('Nombre del almacén')
                                    ->required()
                                    ->maxLength(150),
                                Select::make('tipo')
                                    ->label('Tipo')
                                    ->options(AlmacenTipo::class)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar otro almacén')
                            ->reorderable(false),
                    ]),
            ]);
    }
}
