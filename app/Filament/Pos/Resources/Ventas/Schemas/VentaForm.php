<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Ventas\Schemas;

use App\Enums\TipoPrecioAplicado;
use App\Filament\Support\SelectorProductoVariante;
use App\Models\Cliente;
use App\Models\ListaEscolar;
use App\Models\ProductoVariante;
use App\Services\PrecioService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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
                    ->columnSpanFull()
                    ->components([
                        Select::make('cliente_id')
                            ->label('Cliente registrado')
                            ->options(fn () => Cliente::query()
                                ->orderBy('nombres')
                                ->get()
                                ->mapWithKeys(fn (Cliente $cliente) => [
                                    $cliente->id => trim("{$cliente->nombres} {$cliente->apellidos}")
                                        .($cliente->documento ? " ({$cliente->documento})" : ''),
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
                            ->createOptionUsing(fn (array $data): int => Cliente::create($data)->getKey()),
                        TextInput::make('cliente_temporal')
                            ->label('Nombre para la cola de caja')
                            ->helperText('Ej. "Juan Polera Roja" — para cuando el cliente no está registrado.')
                            ->requiredWithout('cliente_id')
                            ->maxLength(100),
                    ]),
                Section::make('Productos')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('buscador_producto')
                            ->label('Buscar y agregar producto')
                            ->placeholder('Nombre, código interno o código de barras…')
                            ->helperText('Selecciona un resultado para agregarlo a la canasta. También sirve con lector de código de barras.')
                            ->options(fn () => SelectorProductoVariante::opcionesVariantesConProducto())
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get) {
                                if (blank($state)) {
                                    return;
                                }

                                $detalles = static::detallesLimpios($get('detalles'));
                                $detalles = static::agregarOIncrementarDetalle($detalles, (int) $state, 1);

                                $set('detalles', $detalles);
                                $set('buscador_producto', null);
                            }),
                        Select::make('aplicar_lista_escolar')
                            ->label('Aplicar lista escolar')
                            ->placeholder('Elige una plantilla para agregar todos sus productos…')
                            ->helperText('Agrega de una vez todos los productos de la lista a la canasta (sumando cantidades si algún producto ya estaba agregado).')
                            ->options(fn () => ListaEscolar::query()
                                ->where('es_plantilla', true)
                                ->orderBy('nombre_plantilla')
                                ->get()
                                ->mapWithKeys(fn (ListaEscolar $lista) => [
                                    $lista->id => filled($lista->colegio)
                                        ? "{$lista->nombre_plantilla} — {$lista->colegio}"
                                        : $lista->nombre_plantilla,
                                ]))
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get) {
                                if (blank($state)) {
                                    return;
                                }

                                $lista = ListaEscolar::query()->with('detalles.productoVariante')->find($state);
                                $set('aplicar_lista_escolar', null);

                                if ($lista === null) {
                                    return;
                                }

                                $detalles = static::detallesLimpios($get('detalles'));
                                $omitidos = 0;

                                foreach ($lista->detalles as $detalleLista) {
                                    $variante = $detalleLista->productoVariante;

                                    if (! $variante instanceof ProductoVariante || $variante->trashed() || ! $variante->estado) {
                                        $omitidos++;

                                        continue;
                                    }

                                    $detalles = static::agregarOIncrementarDetalle($detalles, $detalleLista->producto_variante_id, $detalleLista->cantidad);
                                }

                                $set('detalles', $detalles);

                                if ($omitidos > 0) {
                                    Notification::make()
                                        ->title('Algunos productos no se agregaron')
                                        ->body("{$omitidos} producto(s) de esta lista ya no están disponibles (descontinuados) y se omitieron.")
                                        ->warning()
                                        ->send();
                                }
                            }),
                        Repeater::make('detalles')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('producto_variante_id')
                                    ->required(),
                                TextEntry::make('detalle_preview')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn (Get $get) => SelectorProductoVariante::renderPreviewVariante($get('producto_variante_id')))
                                    ->columnSpanFull(),
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
                                    ->state(fn (Get $get) => static::previewPrecio(
                                        $get('producto_variante_id'),
                                        $get('cantidad'),
                                        $get('forzar_mayorista'),
                                    ))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->default([])
                            ->minItems(1)
                            ->addable(false)
                            ->reorderable(false)
                            ->itemLabel(function (array $state): ?string {
                                $variante = filled($state['producto_variante_id'] ?? null)
                                    ? ProductoVariante::find($state['producto_variante_id'])
                                    : null;

                                return $variante ? SelectorProductoVariante::labelVarianteConProducto($variante) : null;
                            })
                            ->live(),
                        TextEntry::make('total_estimado')
                            ->label('Total estimado')
                            ->weight('bold')
                            ->state(fn (Get $get) => static::totalEstimado($get('detalles') ?? []))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Quita las líneas todavía sin variante elegida (la fila vacía inicial del Repeater
     * antes de que exista un primer producto agregado). Usado por `buscador_producto` y
     * `aplicar_lista_escolar` antes de mezclar nuevas líneas.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function detallesLimpios(mixed $detalles): array
    {
        return array_values(array_filter(
            $detalles ?? [],
            fn (array $linea) => filled($linea['producto_variante_id'] ?? null)
        ));
    }

    /**
     * Si la variante ya está en la canasta, suma la cantidad a la línea existente en vez
     * de duplicarla; si no, agrega una línea nueva. Mismo criterio tanto para agregar un
     * producto individual como para aplicar una lista escolar completa.
     *
     * @param  array<int, array<string, mixed>>  $detalles
     * @return array<int, array<string, mixed>>
     */
    protected static function agregarOIncrementarDetalle(array $detalles, int $varianteId, int $cantidad): array
    {
        foreach ($detalles as $i => $linea) {
            if ((int) $linea['producto_variante_id'] === $varianteId) {
                $detalles[$i]['cantidad'] = (int) ($detalles[$i]['cantidad'] ?? 0) + $cantidad;

                return $detalles;
            }
        }

        $detalles[] = [
            'producto_variante_id' => $varianteId,
            'cantidad' => $cantidad,
            'forzar_mayorista' => false,
        ];

        return $detalles;
    }

    protected static function previewPrecio(mixed $varianteId, mixed $cantidad, mixed $forzarMayorista): string
    {
        if (blank($varianteId) || blank($cantidad) || (int) $cantidad <= 0) {
            return 'Selecciona variante y cantidad para ver el precio.';
        }

        try {
            $calculado = (new PrecioService)->calcularPrecio((int) $varianteId, (int) $cantidad, (bool) $forzarMayorista);
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
        $precioService = new PrecioService;

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
