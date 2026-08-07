<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Compra;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Detalle de compra compartido entre el panel `admin` y el panel `almacen` — mismo
 * criterio que `CompraForm`/`CompraTable`.
 */
class CompraInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->components([
                        Grid::make(3)
                            ->components([
                                TextEntry::make('proveedor.razon_social')
                                    ->label('Proveedor'),
                                TextEntry::make('almacen_mostrado')
                                    ->label('Almacén')
                                    ->state(fn (Compra $record) => "{$record->almacen->sucursal->nombre} — {$record->almacen->nombre}"),
                                TextEntry::make('estado')
                                    ->label('Estado')
                                    ->badge(),
                                TextEntry::make('numero_factura')
                                    ->label('N° de factura')
                                    ->placeholder('—'),
                                TextEntry::make('total')
                                    ->money('PEN'),
                                TextEntry::make('usuario.nombres')
                                    ->label('Registrada por')
                                    ->formatStateUsing(fn (Compra $record) => "{$record->usuario->nombres} {$record->usuario->apellidos}"),
                                TextEntry::make('created_at')
                                    ->label('Fecha')
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
                                TextEntry::make('precio_compra_unitario')
                                    ->label('Precio de compra')
                                    ->money('PEN'),
                                TextEntry::make('subtotal')
                                    ->money('PEN'),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
