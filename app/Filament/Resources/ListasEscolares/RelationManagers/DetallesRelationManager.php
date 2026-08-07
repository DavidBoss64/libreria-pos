<?php

declare(strict_types=1);

namespace App\Filament\Resources\ListasEscolares\RelationManagers;

use App\Filament\Support\SelectorProductoVariante;
use App\Models\ListaEscolarDetalle;
use App\Models\ProductoVariante;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DetallesRelationManager extends RelationManager
{
    protected static string $relationship = 'detalles';

    protected static ?string $title = 'Productos de la lista';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('producto_id')
                    ->label('Producto')
                    ->options(fn () => SelectorProductoVariante::opcionesProductos())
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Set $set, ?ListaEscolarDetalle $record) {
                        if ($record !== null) {
                            $set('producto_id', $record->productoVariante->producto_id);
                        }
                    })
                    ->afterStateUpdated(fn (Set $set) => $set('producto_variante_id', null))
                    ->required(),
                TextEntry::make('producto_preview')
                    ->hiddenLabel()
                    ->html()
                    ->state(fn (Get $get) => SelectorProductoVariante::renderPreviewProducto($get('producto_id')))
                    ->columnSpanFull(),
                Select::make('producto_variante_id')
                    ->label('Variante')
                    ->options(fn (Get $get) => SelectorProductoVariante::opcionesVariantes($get('producto_id')))
                    ->disabled(fn (Get $get) => blank($get('producto_id')))
                    ->searchable()
                    ->live()
                    ->required(),
                TextInput::make('cantidad')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('productoVariante')
                    ->label('Producto')
                    ->state(fn (ListaEscolarDetalle $record) => $record->productoVariante instanceof ProductoVariante
                        ? SelectorProductoVariante::labelVarianteConProducto($record->productoVariante)
                        : '—'),
                TextColumn::make('cantidad')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('subtotal')
                    ->label('Subtotal (S/)')
                    ->state(fn (ListaEscolarDetalle $record) => $record->productoVariante instanceof ProductoVariante
                        ? number_format((float) $record->productoVariante->precio_venta_unidad * $record->cantidad, 2)
                        : '—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
