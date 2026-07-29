<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Productos\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('imagen_principal')
                    ->label('Imagen')
                    ->disk('public')
                    ->size(300),
                TextEntry::make('nombre'),
                TextEntry::make('marca.nombre')
                    ->label('Marca')
                    ->placeholder('—'),
                TextEntry::make('categoria.nombre')
                    ->label('Categoría'),
                Section::make('Variantes')
                    ->components([
                        RepeatableEntry::make('variantes')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('codigo_interno')
                                    ->label('Código interno'),
                                TextEntry::make('codigo_barras')
                                    ->label('Código de barras')
                                    ->placeholder('—'),
                                TextEntry::make('precio_venta_unidad')
                                    ->label('Unidad')
                                    ->money('PEN'),
                                TextEntry::make('precio_venta_docena')
                                    ->label('Docena')
                                    ->money('PEN'),
                                TextEntry::make('precio_venta_mayor')
                                    ->label('Mayor')
                                    ->money('PEN'),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }
}
