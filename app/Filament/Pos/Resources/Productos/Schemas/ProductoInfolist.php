<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Productos\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema


            ->components([

                Section::make()
                    ->components([
                        Grid::make(['default' => 1, 'md' => 3])

                            ->components([
                                ImageEntry::make('imagen_principal')
                                    ->hiddenLabel()
                                    ->disk('public')
                                    ->imageSize(350)
                                    ->columnSpan(['default' => 1, 'md' => 2]),

                                Grid::make(1)
                                    ->components([
                                        TextEntry::make('nombre')
                                            ->label('Producto')
                                            ->weight('bold')
                                            ->size('lg'),
                                        TextEntry::make('marca.nombre')
                                            ->label('Marca')
                                            ->placeholder('—')
                                            ->badge()
                                            ->color('gray'),
                                        TextEntry::make('categoria.nombre')
                                            ->label('Categoría')
                                            ->badge(),
                                    ])
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                            ]),
                    ]),
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
                                TextEntry::make('atributos')
                                    ->label('Atributos')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—')
                                    ->state(fn(Model $record): array => collect($record->atributos ?? [])
                                        ->map(fn($valor, $clave) => Str::title((string) $clave) . ': ' . $valor)
                                        ->values()
                                        ->all()),
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
                            ->columns(3),
                    ]),
            ]);
    }
}
