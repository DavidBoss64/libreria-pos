<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Productos\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen_principal')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->imageSize(56),
                TextColumn::make('nombre')
                    ->label('Producto')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => collect([$record->marca?->nombre, $record->categoria?->nombre])
                        ->filter()
                        ->implode(' · ')),
                TextColumn::make('marca.nombre')
                    ->label('Marca')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('variantes_count')
                    ->label('Variantes')
                    ->counts('variantes')
                    ->badge()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre'),
                SelectFilter::make('marca_id')
                    ->label('Marca')
                    ->relationship('marca', 'nombre'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
