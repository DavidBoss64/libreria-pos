<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Enums\TipoMovimientoInventario;
use App\Exceptions\StockInsuficienteException;
use App\Models\Almacen;
use App\Models\ProductoVariante;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class AjusteInventario extends Page
{
    protected string $view = 'filament.pages.ajuste-inventario';

    protected static ?string $title = 'Ajuste de Inventario';

    protected static ?string $navigationLabel = 'Ajuste de Inventario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('registrarAjuste')
                ->label('Registrar ajuste')
                ->modalHeading('Registrar ajuste manual de inventario')
                ->modalSubmitActionLabel('Registrar')
                ->schema([
                    Select::make('almacen_id')
                        ->label('Almacén')
                        ->options(fn () => Almacen::query()
                            ->with('sucursal')
                            ->get()
                            ->mapWithKeys(fn (Almacen $almacen) => [
                                $almacen->id => "{$almacen->sucursal->nombre} — {$almacen->nombre} ({$almacen->tipo->getLabel()})",
                            ]))
                        ->searchable()
                        ->required(),
                    Select::make('producto_variante_id')
                        ->label('Producto')
                        ->options(fn () => ProductoVariante::query()
                            ->with('producto')
                            ->get()
                            ->mapWithKeys(fn (ProductoVariante $variante) => [
                                $variante->id => "{$variante->producto->nombre} ({$variante->codigo_interno})",
                            ]))
                        ->searchable()
                        ->required(),
                    TextInput::make('cantidad')
                        ->label('Cantidad (+/-)')
                        ->integer()
                        ->required()
                        ->rule('not_in:0')
                        ->helperText('Usa un número positivo para un ingreso o corrección al alza, y uno negativo para una merma, daño o pérdida.'),
                    Textarea::make('motivo')
                        ->label('Motivo')
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
                }),
        ];
    }
}
