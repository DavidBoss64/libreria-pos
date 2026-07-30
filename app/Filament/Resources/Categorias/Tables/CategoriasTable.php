<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categorias\Tables;

use App\Filament\Support\AccionesPapelera;
use App\Models\Categoria;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CategoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('productos_count')
                    ->label('Productos')
                    ->counts('productos')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AccionesPapelera::deleteBulk(
                        fn (Categoria $categoria) => $categoria->tieneProductosActivos(),
                        'Al menos una de las categorías seleccionadas tiene productos activos asociados. Reasígnalos primero, o quita esa categoría de la selección.',
                    ),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
