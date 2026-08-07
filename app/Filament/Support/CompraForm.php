<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Almacen;
use App\Models\Proveedor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/**
 * Formulario de "Registrar compra" compartido entre el panel `admin` (sin
 * restricción de almacén) y el panel `almacen` (restringido a los almacenes
 * asignados al Almacenero vía `almacen_usuario`) — mismo criterio que
 * `AccionAjusteInventario::make(?Almacen $almacenFijo)`, pero aquí puede haber
 * más de un almacén permitido (modelo "hub and spoke"), no solo uno fijo.
 */
class CompraForm
{
    /**
     * $almacenesPermitidos: `null` = sin restricción (panel `admin`, ve todos los
     * almacenes). Una `Collection` = restringido a esos almacenes (panel `almacen`).
     */
    public static function configure(Schema $schema, ?Collection $almacenesPermitidos = null): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la compra')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('proveedor_id')
                            ->label('Proveedor')
                            ->options(fn () => Proveedor::query()
                                ->where('estado', true)
                                ->orderBy('razon_social')
                                ->pluck('razon_social', 'id'))
                            ->searchable()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('razon_social')->required()->maxLength(255),
                                TextInput::make('nit_documento')->label('RUC / NIT')->maxLength(50),
                                TextInput::make('contacto')->maxLength(150),
                                TextInput::make('telefono')->tel()->maxLength(50),
                            ])
                            ->createOptionUsing(fn (array $data): int => Proveedor::create($data)->getKey()),
                        Select::make('almacen_id')
                            ->label('Almacén de destino')
                            ->options(fn () => static::opcionesAlmacen($almacenesPermitidos))
                            ->default($almacenesPermitidos?->count() === 1 ? $almacenesPermitidos->first()->id : null)
                            ->disabled($almacenesPermitidos?->count() === 1)
                            ->dehydrated()
                            ->searchable()
                            ->required(),
                        TextInput::make('numero_factura')
                            ->label('Número de factura')
                            ->maxLength(100)
                            ->columnSpanFull(),
                    ]),
                Section::make('Productos recibidos')
                    ->columnSpanFull()
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
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, mixed $state) => CamposCantidadPorCaja::sincronizarUnidadesPorCaja($set, $state))
                                    ->required(),
                                ...CamposCantidadPorCaja::campos(permitirNegativo: false),
                                TextInput::make('precio_compra_unitario')
                                    ->label('Precio de compra (por unidad)')
                                    ->numeric()
                                    ->required()
                                    ->prefix('S/')
                                    ->live(onBlur: true),
                                TextEntry::make('subtotal_preview')
                                    ->hiddenLabel()
                                    ->weight('bold')
                                    ->state(fn (Get $get) => static::previewSubtotalLinea($get))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->default([[]])
                            ->addActionLabel('Agregar otro producto')
                            ->reorderable(false)
                            ->live(),
                    ]),
                Section::make('Resumen')
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('total_estimado')
                            ->label('Total de la compra')
                            ->weight('bold')
                            ->size('lg')
                            ->state(fn (Get $get) => static::previewTotal($get('detalles') ?? []))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function opcionesAlmacen(?Collection $almacenesPermitidos): array
    {
        $almacenes = $almacenesPermitidos ?? Almacen::query()->with('sucursal')->get();

        return $almacenes
            ->mapWithKeys(fn (Almacen $almacen) => [
                $almacen->id => "{$almacen->sucursal->nombre} — {$almacen->nombre} ({$almacen->tipo->getLabel()})",
            ])
            ->all();
    }

    private static function previewSubtotalLinea(Get $get): string
    {
        $linea = [
            'modo_cantidad' => $get('modo_cantidad'),
            'cantidad' => $get('cantidad'),
            'cantidad_cajas' => $get('cantidad_cajas'),
            'unidades_por_caja' => $get('unidades_por_caja'),
        ];

        $precio = $get('precio_compra_unitario');

        if (blank($precio) || ! is_numeric($precio)) {
            return 'Completa la cantidad y el precio de compra para ver el subtotal.';
        }

        $cantidad = CamposCantidadPorCaja::cantidadFinalEnUnidades($linea);

        if ($cantidad <= 0) {
            return 'Completa la cantidad y el precio de compra para ver el subtotal.';
        }

        $subtotal = bcmul((string) $cantidad, (string) $precio, 2);

        return "Subtotal: S/ {$subtotal} ({$cantidad} unidades)";
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     */
    private static function previewTotal(array $detalles): string
    {
        $total = '0.00';

        foreach ($detalles as $linea) {
            $precio = $linea['precio_compra_unitario'] ?? null;

            if (blank($precio) || ! is_numeric($precio)) {
                continue;
            }

            $cantidad = CamposCantidadPorCaja::cantidadFinalEnUnidades($linea);

            if ($cantidad <= 0) {
                continue;
            }

            $total = bcadd($total, bcmul((string) $cantidad, (string) $precio, 2), 2);
        }

        return "S/ {$total}";
    }
}
