<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Traspasos\Tables;

use App\Enums\TraspasoEstado;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TraspasosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('almacenOrigen.nombre')
                    ->label('Origen')
                    ->sortable(),
                TextColumn::make('almacenDestino.nombre')
                    ->label('Destino')
                    ->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('detalles_count')
                    ->label('Productos')
                    ->counts('detalles')
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(TraspasoEstado::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
