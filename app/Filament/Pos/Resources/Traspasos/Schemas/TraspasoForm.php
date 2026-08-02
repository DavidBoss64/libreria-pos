<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Traspasos\Schemas;

use App\Enums\AlmacenTipo;
use App\Filament\Support\SelectorProductoVariante;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TraspasoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Traspaso')
                    ->columns(2)
                    ->components([
                        Select::make('almacen_origen_id')
                            ->label('Almacén origen (depósito)')
                            ->options(fn () => Almacen::query()
                                ->where('tipo', AlmacenTipo::Deposito)
                                ->where('estado', true)
                                ->pluck('nombre', 'id'))
                            ->searchable()
                            ->live()
                            ->required(),
                        Select::make('almacen_destino_id')
                            ->label('Almacén destino (mi sucursal)')
                            ->options(fn () => Almacen::query()
                                ->with('sucursal')
                                ->where('sucursal_id', Auth::user()?->sucursal_id)
                                ->where('tipo', AlmacenTipo::Tienda)
                                ->where('estado', true)
                                ->get()
                                ->mapWithKeys(fn (Almacen $almacen) => [
                                    $almacen->id => "{$almacen->sucursal->nombre} — {$almacen->nombre}",
                                ]))
                            ->searchable()
                            ->required(),
                    ]),
                Section::make('Productos solicitados')
                    ->components([
                        Repeater::make('detalles')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('producto_id')
                                    ->label('Producto')
                                    ->options(fn () => Producto::query()
                                        ->with(['marca', 'categoria'])
                                        ->where('estado', true)
                                        ->get()
                                        ->mapWithKeys(fn (Producto $producto) => [
                                            $producto->id => collect([
                                                $producto->nombre,
                                                $producto->marca?->nombre,
                                                $producto->categoria?->nombre,
                                            ])->filter()->implode(' — '),
                                        ]))
                                    ->searchable()
                                    ->live()
                                    ->dehydrated(false)
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
                                    ->helperText(fn (Get $get) => static::ayudaVariante($get('producto_id'), $get('producto_variante_id'), $get('../../almacen_origen_id')))
                                    ->searchable()
                                    ->live()
                                    ->required(),
                                TextInput::make('cantidad')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar otro producto')
                            ->reorderable(false),
                    ]),
            ]);
    }

    protected static function ayudaVariante(mixed $productoId, mixed $varianteId, mixed $almacenOrigenId): ?string
    {
        if (blank($productoId)) {
            return 'Selecciona primero un producto.';
        }

        if (blank($varianteId) || blank($almacenOrigenId)) {
            return null;
        }

        $inventario = Inventario::query()
            ->where('almacen_id', $almacenOrigenId)
            ->where('producto_variante_id', $varianteId)
            ->first();

        $disponible = $inventario->cantidad ?? 0;

        $texto = "Disponible en el almacén origen: {$disponible}";

        if ($inventario && $inventario->cantidad <= $inventario->stock_minimo) {
            $texto .= ' (stock bajo)';
        }

        return $texto;
    }
}
