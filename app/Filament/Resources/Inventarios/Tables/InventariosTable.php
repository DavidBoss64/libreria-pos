<?php

declare(strict_types=1);

namespace App\Filament\Resources\Inventarios\Tables;

use App\Models\Sucursal;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(fn ($record) => $record->cantidad <= $record->stock_minimo
                ? '[&>td]:bg-danger-50 dark:[&>td]:bg-danger-500/10'
                : null)
            ->columns([
                TextColumn::make('almacen.sucursal.nombre')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
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
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->cantidad <= $record->stock_minimo ? 'danger' : 'success'),
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
                    ->relationship('almacen', 'nombre'),
                SelectFilter::make('producto_variante_id')
                    ->label('Producto (variante)')
                    ->relationship('productoVariante', 'codigo_interno')
                    ->searchable(),
                SelectFilter::make('sucursal_id')
                    ->label('Sucursal')
                    ->options(fn () => Sucursal::pluck('nombre', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $sucursalId) => $q->whereHas(
                                'almacen',
                                fn (Builder $q2) => $q2->where('sucursal_id', $sucursalId)
                            ),
                        );
                    }),
                TernaryFilter::make('stock_bajo')
                    ->label('Stock bajo')
                    ->queries(
                        true: fn (Builder $query) => $query->whereColumn('cantidad', '<=', 'stock_minimo'),
                        false: fn (Builder $query) => $query->whereColumn('cantidad', '>', 'stock_minimo'),
                    ),
            ])
            ->defaultSort('cantidad');
    }
}
