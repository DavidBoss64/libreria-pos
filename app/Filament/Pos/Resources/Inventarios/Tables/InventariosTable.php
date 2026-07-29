<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Inventarios\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->description(fn ($record) => $record->productoVariante->codigo_interno),
                TextColumn::make('cantidad')
                    ->label('Disponible')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->cantidad <= $record->stock_minimo ? 'danger' : 'success'),
                TextColumn::make('cantidad_comprometida')
                    ->label('Comprometido')
                    ->sortable(),
            ])
            ->defaultSort('cantidad');
    }
}
