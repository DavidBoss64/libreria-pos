<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Ventas\Schemas;

use App\Enums\TipoPrecioAplicado;
use App\Filament\Support\SelectorProductoVariante;
use App\Models\Cliente;
use App\Services\PrecioService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Throwable;

class VentaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente')
                    ->columns(2)
                    ->components([
                        Select::make('cliente_id')
                            ->label('Cliente registrado')
                            ->options(fn() => Cliente::query()
                                ->orderBy('nombres')
                                ->get()
                                ->mapWithKeys(fn(Cliente $cliente) => [
                                    $cliente->id => trim("{$cliente->nombres} {$cliente->apellidos}")
                                        . ($cliente->documento ? " ({$cliente->documento})" : ''),
                                ]))
                            ->searchable()
                            ->requiredWithout('cliente_temporal')
                            ->createOptionForm([
                                TextInput::make('nombres')->required()->maxLength(255),
                                TextInput::make('apellidos')->required()->maxLength(255),
                                TextInput::make('documento')->maxLength(20),
                                TextInput::make('telefono')->tel()->maxLength(50),
                                TextInput::make('email')->email()->maxLength(150),
                            ])
                            ->createOptionUsing(fn(array $data): int => Cliente::create($data)->getKey()),
                        TextInput::make('cliente_temporal')
                            ->label('Nombre para la cola de caja')
                            ->helperText('Ej. "Juan Polera Roja" — para cuando el cliente no está registrado.')
                            ->requiredWithout('cliente_id')
                            ->maxLength(100),
                    ]),
                Section::make('Productos')
                    ->components([
                        Repeater::make('detalles')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('producto_id')
                                    ->label('Producto')
                                    ->options(fn() => SelectorProductoVariante::opcionesProductos())
                                    ->searchable()
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(fn(Set $set) => $set('producto_variante_id', null))
                                    ->required(),
                                TextEntry::make('producto_preview')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn(Get $get) => SelectorProductoVariante::renderPreviewProducto($get('producto_id')))
                                    ->columnSpanFull(),
                                Select::make('producto_variante_id')
                                    ->label('Variante')
                                    ->options(fn(Get $get) => SelectorProductoVariante::opcionesVariantes($get('producto_id')))
                                    ->disabled(fn(Get $get) => blank($get('producto_id')))
                                    ->searchable()
                                    ->live()
                                    ->required(),
                                TextInput::make('cantidad')
                                    ->numeric()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->required(),
                                Toggle::make('forzar_mayorista')
                                    ->label('Forzar precio mayorista')
                                    ->helperText('Para clientes recurrentes, sin importar la cantidad.')
                                    ->live()
                                    ->default(false),
                                TextEntry::make('precio_preview')
                                    ->hiddenLabel()
                                    ->state(fn(Get $get) => static::previewPrecio(
                                        $get('producto_variante_id'),
                                        $get('cantidad'),
                                        $get('forzar_mayorista'),
                                    ))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->default([[]])
                            ->addActionLabel('Agregar otro producto')
                            ->reorderable(false)
                            ->live(),
                        TextEntry::make('total_estimado')
                            ->label('Total estimado')
                            ->weight('bold')
                            ->state(fn(Get $get) => static::totalEstimado($get('detalles') ?? []))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function previewPrecio(mixed $varianteId, mixed $cantidad, mixed $forzarMayorista): string
    {
        if (blank($varianteId) || blank($cantidad) || (int) $cantidad <= 0) {
            return 'Selecciona variante y cantidad para ver el precio.';
        }

        try {
            $calculado = (new PrecioService())->calcularPrecio((int) $varianteId, (int) $cantidad, (bool) $forzarMayorista);
        } catch (Throwable) {
            return 'No se pudo calcular el precio.';
        }

        $etiquetaTipo = match ($calculado->tipo) {
            TipoPrecioAplicado::Unidad => 'Unidad',
            TipoPrecioAplicado::Docena => 'Docena',
            TipoPrecioAplicado::Mayor => 'Mayorista',
        };

        return "Precio {$etiquetaTipo}: S/ {$calculado->precioUnitario} — Subtotal: S/ {$calculado->subtotal}";
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     */
    protected static function totalEstimado(array $detalles): string
    {
        $total = '0.00';
        $precioService = new PrecioService();

        foreach ($detalles as $linea) {
            $varianteId = $linea['producto_variante_id'] ?? null;
            $cantidad = (int) ($linea['cantidad'] ?? 0);

            if (blank($varianteId) || $cantidad <= 0) {
                continue;
            }

            try {
                $calculado = $precioService->calcularPrecio((int) $varianteId, $cantidad, (bool) ($linea['forzar_mayorista'] ?? false));
                $total = bcadd($total, $calculado->subtotal, 2);
            } catch (Throwable) {
                continue;
            }
        }
        return "S/ {$total}";
    }
}
