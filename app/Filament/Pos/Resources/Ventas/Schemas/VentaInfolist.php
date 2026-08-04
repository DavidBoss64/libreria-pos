<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Ventas\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VentaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->components([
                        Grid::make(3)
                            ->components([
                                TextEntry::make('numero_ticket')
                                    ->label('Ticket')
                                    ->placeholder('—'),
                                TextEntry::make('estado')
                                    ->label('Estado')
                                    ->badge(),
                                TextEntry::make('cliente_mostrado')
                                    ->label('Cliente')
                                    ->state(fn ($record) => $record->cliente
                                        ? trim("{$record->cliente->nombres} {$record->cliente->apellidos}")
                                        : $record->cliente_temporal),
                                TextEntry::make('vendedor.nombres')
                                    ->label('Vendedor')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn ($record) => $record->vendedor
                                        ? "{$record->vendedor->nombres} {$record->vendedor->apellidos}"
                                        : '—'),
                                TextEntry::make('usuario.nombres')
                                    ->label('Cajero')
                                    ->placeholder('Sin cobrar todavía')
                                    ->formatStateUsing(fn ($record) => $record->usuario
                                        ? "{$record->usuario->nombres} {$record->usuario->apellidos}"
                                        : 'Sin cobrar todavía'),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->money('PEN'),
                                TextEntry::make('metodo_pago')
                                    ->label('Método de pago')
                                    ->badge()
                                    ->placeholder('—'),
                                TextEntry::make('referencia_pago')
                                    ->label('Referencia de pago')
                                    ->placeholder('—'),
                                TextEntry::make('expira_en')
                                    ->label('Expira')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Creada')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ]),
                Section::make('Productos')
                    ->components([
                        RepeatableEntry::make('detalles')
                            ->hiddenLabel()
                            ->schema([
                                ImageEntry::make('productoVariante.producto.imagen_principal')
                                    ->hiddenLabel()
                                    ->circular()
                                    ->imageSize(48),
                                TextEntry::make('productoVariante.producto.nombre')
                                    ->label('Producto')
                                    ->weight('bold'),
                                TextEntry::make('marca_categoria')
                                    ->label('Marca / Categoría')
                                    ->placeholder('—')
                                    ->state(fn ($record) => collect([
                                        $record->productoVariante->producto->marca?->nombre,
                                        $record->productoVariante->producto->categoria?->nombre,
                                    ])->filter()->implode(' · ')),
                                TextEntry::make('atributos')
                                    ->label('Variante')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—')
                                    ->state(fn (Model $record): array => collect($record->productoVariante->atributos ?? [])
                                        ->map(fn ($valor, $clave) => Str::title((string) $clave).': '.$valor)
                                        ->values()
                                        ->all()),
                                TextEntry::make('cantidad')
                                    ->badge(),
                                TextEntry::make('tipo_precio_aplicado')
                                    ->label('Precio aplicado')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('precio_unitario')
                                    ->money('PEN'),
                                TextEntry::make('subtotal')
                                    ->money('PEN'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
