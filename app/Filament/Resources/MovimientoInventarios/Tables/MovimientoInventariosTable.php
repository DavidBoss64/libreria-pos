<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovimientoInventarios\Tables;

use App\Enums\TipoMovimientoInventario;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MovimientoInventariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
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
                TextColumn::make('tipo_movimiento')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->sortable(),
                TextColumn::make('saldo_despues')
                    ->label('Saldo después')
                    ->sortable(),
                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('usuario.nombres')
                    ->label('Usuario')
                    ->formatStateUsing(fn ($record) => $record->usuario
                        ? "{$record->usuario->nombres} {$record->usuario->apellidos}"
                        : '—'),
            ])
            ->filters([
                SelectFilter::make('almacen_id')
                    ->label('Almacén')
                    ->relationship('almacen', 'nombre'),
                SelectFilter::make('producto_variante_id')
                    ->label('Producto (variante)')
                    ->relationship('productoVariante', 'codigo_interno')
                    ->searchable(),
                SelectFilter::make('tipo_movimiento')
                    ->label('Tipo')
                    ->options(TipoMovimientoInventario::class),
                SelectFilter::make('usuario_id')
                    ->label('Usuario')
                    ->relationship('usuario', 'nombres')
                    ->searchable(),
                Filter::make('created_at')
                    ->label('Rango de fechas')
                    ->schema([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $fecha) => $q->whereDate('created_at', '>=', $fecha),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $fecha) => $q->whereDate('created_at', '<=', $fecha),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
