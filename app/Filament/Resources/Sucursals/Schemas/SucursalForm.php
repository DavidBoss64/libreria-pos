<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sucursals\Schemas;

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
                    ->description('Al crear la sucursal se genera automáticamente su Almacén de tipo Tienda. Puedes agregar almacenes adicionales (ej. un depósito) después, desde el Resource de Almacenes.')
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
            ]);
    }
}
