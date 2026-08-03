<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Inventarios\Tables;

use App\Enums\AlmacenTipo;
use App\Filament\Pos\Resources\Traspasos\TraspasoResource;
use App\Filament\Support\SelectorProductoVariante;
use App\Models\Inventario;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InventariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'almacen.sucursal',
                'productoVariante.producto.marca',
                'productoVariante.producto.categoria',
            ]))
            ->recordClasses(fn ($record) => $record->cantidad <= $record->stock_minimo
                ? '[&>td]:bg-danger-50 dark:[&>td]:bg-danger-500/10'
                : null)
            ->columns([
                ImageColumn::make('productoVariante.producto.imagen_principal')
                    ->label('')
                    ->imageSize(48)
                    ->square(),
                TextColumn::make('almacen.sucursal.nombre')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('almacen.nombre')
                    ->label('Almacén')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('productoVariante.producto.nombre')
                    ->label('Producto')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Inventario $record) => SelectorProductoVariante::labelVariante($record->productoVariante)),
                TextColumn::make('productoVariante.producto.marca.nombre')
                    ->label('Marca')
                    ->toggleable(),
                TextColumn::make('productoVariante.producto.categoria.nombre')
                    ->label('Categoría')
                    ->toggleable(),
                TextColumn::make('cantidad')
                    ->label('Disponible')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->cantidad <= $record->stock_minimo ? 'danger' : 'success'),
                TextColumn::make('cantidad_comprometida')
                    ->label('Comprometido')
                    ->sortable(),
            ])
            ->recordActions([
                // Visible en las dos pestañas: en "Almacenes (traspaso)" la fila ya
                // es un depósito, así que se precarga como almacén origen. En "Mi
                // sucursal" la fila es la tienda propia (nunca un origen válido) —
                // se precarga igual el producto/variante, pero el Vendedor elige el
                // depósito de origen en el formulario, como haría manualmente.
                Action::make('solicitarTraspaso')
                    ->label('Solicitar')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('primary')
                    ->url(fn (Inventario $record) => TraspasoResource::getUrl('create', array_filter([
                        'almacen_origen_id' => $record->almacen->tipo === AlmacenTipo::Deposito ? $record->almacen_id : null,
                        'producto_variante_id' => $record->producto_variante_id,
                    ]))),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Mismo criterio que la fila individual: siempre redirige con los
                    // productos elegidos. Si la selección completa viene de un único
                    // almacén depósito (típico al navegar la pestaña "Almacenes"), se
                    // precarga también el origen; si viene de "Mi sucursal", de varios
                    // depósitos a la vez, o mezclada, se deja que el Vendedor elija el
                    // origen en el formulario — nunca bloquea la selección.
                    BulkAction::make('solicitarTraspasoMasivo')
                        ->label('Solicitar traspaso de los seleccionados')
                        ->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->color('primary')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $almacenesDeposito = $records
                                ->filter(fn (Inventario $inventario) => $inventario->almacen->tipo === AlmacenTipo::Deposito)
                                ->pluck('almacen_id')
                                ->unique();

                            redirect(TraspasoResource::getUrl('create', array_filter([
                                'almacen_origen_id' => $almacenesDeposito->count() === 1 ? $almacenesDeposito->first() : null,
                                'variantes' => $records->pluck('producto_variante_id')->unique()->implode(','),
                            ])));
                        }),
                ]),
            ])
            ->defaultSort('cantidad');
    }
}
