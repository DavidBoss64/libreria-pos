<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Traspasos\Schemas;

use App\Enums\AlmacenTipo;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
                                    ->state(fn (Get $get) => static::renderPreviewProducto($get('producto_id')))
                                    ->columnSpanFull(),
                                Select::make('producto_variante_id')
                                    ->label('Variante')
                                    ->options(fn (Get $get) => static::opcionesVariantes($get('producto_id')))
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

    protected static function renderPreviewProducto(mixed $productoId): HtmlString|string
    {
        if (blank($productoId)) {
            return 'Selecciona un producto para ver su detalle.';
        }

        $producto = Producto::query()->with(['marca', 'categoria'])->find($productoId);

        if (! $producto) {
            return 'Selecciona un producto para ver su detalle.';
        }

        $imagenHtml = (filled($producto->imagen_principal) && filter_var($producto->imagen_principal, FILTER_VALIDATE_URL))
            ? '<img src="' . e($producto->imagen_principal) . '" style="height:56px;width:56px;object-fit:cover;border-radius:0.5rem;flex-shrink:0;" onerror="this.style.display=\'none\'">'
            : '<div style="height:56px;width:56px;border-radius:0.5rem;background:rgb(228 228 231 / 0.5);flex-shrink:0;"></div>';

        $badges = collect([
            $producto->marca?->nombre,
            $producto->categoria?->nombre,
        ])->filter()
            ->map(fn (string $texto) => '<span style="display:inline-block;padding:0.125rem 0.5rem;margin-right:0.25rem;border-radius:9999px;background:rgb(228 228 231 / 0.7);font-size:0.75rem;">' . e($texto) . '</span>')
            ->implode('');

        return new HtmlString(
            '<div style="display:flex;align-items:center;gap:0.75rem;">'
                . $imagenHtml
                . '<div><div style="font-weight:600;">' . e($producto->nombre) . '</div><div style="margin-top:0.25rem;">' . $badges . '</div></div>'
                . '</div>'
        );
    }

    /**
     * @return array<int, string>
     */
    protected static function opcionesVariantes(mixed $productoId): array
    {
        if (blank($productoId)) {
            return [];
        }

        return ProductoVariante::query()
            ->where('producto_id', $productoId)
            ->where('estado', true)
            ->get()
            ->mapWithKeys(fn (ProductoVariante $variante) => [
                $variante->id => static::labelVariante($variante),
            ])
            ->all();
    }

    protected static function labelVariante(ProductoVariante $variante): string
    {
        $atributos = collect($variante->atributos ?? [])
            ->map(fn ($valor, $clave) => Str::title((string) $clave) . ': ' . $valor)
            ->implode(', ');

        return $atributos !== ''
            ? "{$atributos} ({$variante->codigo_interno})"
            : $variante->codigo_interno;
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
