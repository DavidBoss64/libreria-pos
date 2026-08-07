<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proveedores\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProveedorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('razon_social')
                    ->required()
                    ->maxLength(255),
                TextInput::make('nit_documento')
                    ->label('RUC / NIT')
                    ->maxLength(50),
                TextInput::make('contacto')
                    ->maxLength(150),
                TextInput::make('telefono')
                    ->tel()
                    ->maxLength(50),
                Toggle::make('estado')
                    ->default(true)
                    ->required(),
            ]);
    }
}
