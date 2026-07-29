<?php

declare(strict_types=1);

namespace App\Filament\Almacen\Resources\Traspasos\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TraspasoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->components([
                        Grid::make(3)
                            ->components([
                                TextEntry::make('almacenOrigen.nombre')
                                    ->label('Origen'),
                                TextEntry::make('almacenDestino.nombre')
                                    ->label('Destino'),
                                TextEntry::make('estado')
                                    ->label('Estado')
                                    ->badge(),
                                TextEntry::make('usuarioSolicitante.nombres')
                                    ->label('Solicitado por')
                                    ->formatStateUsing(fn ($record) => "{$record->usuarioSolicitante->nombres} {$record->usuarioSolicitante->apellidos}"),
                                TextEntry::make('usuarioProcesador.nombres')
                                    ->label('Procesado por')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn ($record) => $record->usuarioProcesador
                                        ? "{$record->usuarioProcesador->nombres} {$record->usuarioProcesador->apellidos}"
                                        : null),
                                TextEntry::make('created_at')
                                    ->label('Fecha de solicitud')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ]),
                Section::make('Productos solicitados')
                    ->components([
                        RepeatableEntry::make('detalles')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('productoVariante.producto.nombre')
                                    ->label('Producto'),
                                TextEntry::make('productoVariante.codigo_interno')
                                    ->label('Código'),
                                TextEntry::make('cantidad')
                                    ->label('Cantidad')
                                    ->badge(),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
