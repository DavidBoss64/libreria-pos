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
                    ->circular(),
                TextColumn::make('nombre')
                    ->searchable(),
                TextColumn::make('marca.nombre')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variantes_count')
                    ->label('Variantes')
                    ->counts('variantes')
                    ->badge(),
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
