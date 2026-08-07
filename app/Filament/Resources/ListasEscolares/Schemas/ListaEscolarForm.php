<?php

declare(strict_types=1);

namespace App\Filament\Resources\ListasEscolares\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ListaEscolarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre_plantilla')
                    ->label('Nombre de la plantilla')
                    ->placeholder('Ej. "1ro Básico - Colegio San Calixto"')
                    ->required()
                    ->maxLength(255),
                TextInput::make('colegio')
                    ->maxLength(255),
                TextInput::make('precio_total_estimado')
                    ->label('Precio total estimado')
                    ->prefix('S/')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Se calcula solo, sumando el precio unitario de cada producto agregado abajo. Guarda la plantilla primero para poder agregar productos.'),
            ]);
    }
}
