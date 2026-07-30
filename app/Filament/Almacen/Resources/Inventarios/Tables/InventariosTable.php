<?php

declare(strict_types=1);

namespace App\Filament\Almacen\Resources\Inventarios\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InventariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('almacen.nombre')
                    ->label('Almacén')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('productoVariante.producto.nombre')
                    ->label('Producto')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn($record) => $record->productoVariante->codigo_interno),
                TextColumn::make('cantidad')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn($record) => $record->cantidad <= $record->stock_minimo ? 'danger' : 'success'),
                TextColumn::make('cantidad_comprometida')
                    ->label('Comprometido')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_minimo')
                    ->label('Mínimo')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('almacen_id')
                    ->label('Almacén')
                    // No usa ->relationship(): esa opción lista TODOS los almacenes sin
                    // respetar AislaPorAlmacen, filtrando el nombre de almacenes ajenos
                    // en el dropdown aunque la tabla ya los oculte.
                    ->options(fn() => Auth::user()->almacenes()->pluck('almacenes.nombre', 'almacenes.id')),
                TernaryFilter::make('stock_bajo')
                    ->label('Stock bajo')
                    ->queries(
                        true: fn($query) => $query->whereColumn('cantidad', '<=', 'stock_minimo'),
                        false: fn($query) => $query->whereColumn('cantidad', '>', 'stock_minimo'),
                    ),
            ])
            ->defaultSort('cantidad');
    }
}
