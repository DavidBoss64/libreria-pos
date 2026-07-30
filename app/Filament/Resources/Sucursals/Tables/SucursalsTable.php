<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sucursals\Tables;

use App\Filament\Support\AccionesPapelera;
use App\Models\Sucursal;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SucursalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable(),
                TextColumn::make('direccion')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('almacenes_count')
                    ->label('Almacenes')
                    ->counts('almacenes')
                    ->badge(),
                IconColumn::make('estado')
                    ->boolean(),
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
                        fn (Sucursal $sucursal) => $sucursal->tieneStockFisico(),
                        'Al menos una de las sucursales seleccionadas tiene stock físico registrado en alguno de sus almacenes. Quítala de la selección o vacía ese stock primero.',
                    ),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
