<?php

namespace App\Filament\Resources\Almacens\Tables;

use App\Filament\Resources\Almacens\Pages\VerStockAlmacen;
use App\Filament\Support\AccionesPapelera;
use App\Models\Almacen;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AlmacensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable(),
                TextColumn::make('sucursal.nombre')
                    ->label('Sucursal')
                    ->searchable(),
                TextColumn::make('tipo')
                    ->badge()
                    ->searchable(),
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
                Action::make('verStock')
                    ->label('Ver stock')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->url(fn(Almacen $record) => VerStockAlmacen::getUrl(['record' => $record]))
                    ->visible(fn(Almacen $record) => ! $record->trashed()),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AccionesPapelera::deleteBulk(
                        fn(Almacen $almacen) => $almacen->tieneStockFisico(),
                        'Al menos uno de los almacenes seleccionados todavía tiene stock físico registrado. Traspasa o ajusta ese stock a cero primero, o quítalo de la selección.',
                    ),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
