<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Enums\TipoMovimientoInventario;
use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;

/**
 * Factory compartido del modal "Registrar ajuste manual de inventario"
 * (Paso 3.4, ampliado en 3.8). Lo usa tanto la Page `AjusteInventario`
 * (atajo desde el menú, sin almacén predefinido) como `VerStockAlmacen`
 * (entrando ya con el almacén fijado). El selector de producto reutiliza
 * la cascada Producto → Variante de `SelectorProductoVariante` en vez del
 * `->options()` plano original, que solo mostraba nombre + código interno.
 */
class AccionAjusteInventario
{
    public static function make(?Almacen $almacenFijo = null): Action
    {
        return Action::make('registrarAjuste')
            ->label('Registrar ajuste')
            ->modalHeading($almacenFijo !== null
                ? "Registrar ajuste — {$almacenFijo->sucursal->nombre} — {$almacenFijo->nombre}"
                : 'Registrar ajuste manual de inventario')
            ->modalSubmitActionLabel('Registrar')
            ->schema([
                Select::make('almacen_id')
                    ->label('Almacén')
                    ->options(fn() => $almacenFijo !== null
                        ? [$almacenFijo->id => "{$almacenFijo->sucursal->nombre} — {$almacenFijo->nombre} ({$almacenFijo->tipo->getLabel()})"]
                        : Almacen::query()
                        ->with('sucursal')
                        ->get()
                        ->mapWithKeys(fn(Almacen $almacen) => [
                            $almacen->id => "{$almacen->sucursal->nombre} — {$almacen->nombre} ({$almacen->tipo->getLabel()})",
                        ]))
                    ->default($almacenFijo?->id)
                    ->disabled($almacenFijo !== null)
                    ->dehydrated()
                    ->live()
                    ->searchable()
                    ->required(),
                Select::make('producto_id')
                    ->label('Producto')
                    ->options(fn() => Producto::query()
                        ->with(['marca', 'categoria'])
                        ->where('estado', true)
                        ->get()
                        ->mapWithKeys(fn(Producto $producto) => [
                            $producto->id => collect([
                                $producto->nombre,
                                $producto->marca?->nombre,
                                $producto->categoria?->nombre,
                            ])->filter()->implode(' — '),
                        ]))
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
                    ->helperText(fn(Get $get) => static::ayudaVariante($get('producto_variante_id'), $get('almacen_id')))
                    ->searchable()
                    ->live()
                    ->required(),
                TextInput::make('cantidad')
                    ->label('Cantidad (+/-)')
                    ->integer()
                    ->required()
                    ->rule('not_in:0')
                    ->helperText('Usa un número positivo para un ingreso o corrección al alza, y uno negativo para una merma, daño o pérdida.'),
                Textarea::make('motivo')
                    ->label('Motivo/Observación')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Obligatorio: describe la razón del ajuste (ej. "Merma por daño en almacén", "Corrección de conteo físico").'),
            ])
            ->action(function (array $data, Action $action): void {
                try {
                    app(RegistrarMovimientoInventarioAction::class)->handle(
                        almacenId: (int) $data['almacen_id'],
                        productoVarianteId: (int) $data['producto_variante_id'],
                        tipoMovimiento: TipoMovimientoInventario::Ajuste,
                        cantidad: (int) $data['cantidad'],
                        motivo: $data['motivo'],
                        usuarioId: Auth::id(),
                    );

                    Notification::make()
                        ->title('Ajuste registrado correctamente')
                        ->success()
                        ->send();
                } catch (StockInsuficienteException $e) {
                    Notification::make()
                        ->title('No se pudo registrar el ajuste')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    $action->halt();
                }
            });
    }

    private static function ayudaVariante(mixed $varianteId, mixed $almacenId): ?string
    {
        if (blank($varianteId) || blank($almacenId)) {
            return null;
        }

        $inventario = Inventario::query()
            ->where('almacen_id', $almacenId)
            ->where('producto_variante_id', $varianteId)
            ->first();

        $disponible = $inventario->cantidad ?? 0;

        $texto = "Stock actual en este almacén: {$disponible}";

        if ($inventario && $inventario->cantidad <= $inventario->stock_minimo) {
            $texto .= ' (stock bajo)';
        }

        return $texto;
    }
}
