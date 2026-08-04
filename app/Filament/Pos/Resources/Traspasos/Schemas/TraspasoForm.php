<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Traspasos\Schemas;

use App\Enums\AlmacenTipo;
use App\Filament\Support\SelectorProductoVariante;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\ProductoVariante;
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
                            // Precargado cuando se llega desde el botón "Solicitar" o la
                            // selección múltiple de `Pos\InventarioResource` (query string
                            // `almacen_origen_id`); en el alta manual no hay query string,
                            // así que no cambia nada.
                            ->default(fn () => request()->integer('almacen_origen_id') ?: null)
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
                            // El Vendedor casi siempre pide para la única tienda de su
                            // propia sucursal — se precarga como conveniencia, sigue
                            // siendo editable si algún día hay más de una.
                            ->default(fn () => Almacen::query()
                                ->where('sucursal_id', Auth::user()?->sucursal_id)
                                ->where('tipo', AlmacenTipo::Tienda)
                                ->where('estado', true)
                                ->value('id'))
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
                                    ->options(fn () => SelectorProductoVariante::opcionesProductos())
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
                            ->default(fn () => static::itemsIniciales())
                            ->addActionLabel('Agregar otro producto')
                            ->reorderable(false),
                    ]),
            ]);
    }

    /**
     * Líneas iniciales del Repeater: por defecto una fila vacía (comportamiento
     * de siempre), salvo que se llegue desde el botón "Solicitar" de una fila
     * de `Pos\InventarioResource` (query string `producto_variante_id`) o desde
     * su selección múltiple (`variantes`, CSV de IDs) — ahí se precargan las
     * variantes elegidas con cantidad 1, y el Vendedor solo ajusta y confirma.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function itemsIniciales(): array
    {
        $ids = collect(explode(',', (string) request()->query('variantes', '')))
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter();

        if ($ids->isEmpty() && request()->filled('producto_variante_id')) {
            $ids = collect([request()->integer('producto_variante_id')]);
        }

        if ($ids->isEmpty()) {
            return [[]];
        }

        $variantesPorId = ProductoVariante::query()->whereIn('id', $ids)->get()->keyBy('id');

        $items = $ids->unique()
            ->map(fn (int $id) => $variantesPorId->get($id))
            ->filter()
            ->map(fn (ProductoVariante $variante) => [
                'producto_id' => $variante->producto_id,
                'producto_variante_id' => $variante->id,
                'cantidad' => 1,
            ])
            ->values()
            ->all();

        return $items === [] ? [[]] : $items;
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
