<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Ventas\Tables;

use App\Actions\Ventas\CancelarPreventaAction;
use App\Actions\Ventas\CerrarVentaAction;
use App\Enums\VentaEstado;
use App\Enums\VentaMetodoPago;
use App\Exceptions\PuntosInsuficientesException;
use App\Exceptions\VentaSinStockDisponibleException;
use App\Models\Venta;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class VentasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_ticket')
                    ->label('Ticket')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('cliente_mostrado')
                    ->label('Cliente')
                    ->state(fn (Venta $record) => $record->cliente
                        ? trim("{$record->cliente->nombres} {$record->cliente->apellidos}")
                        : $record->cliente_temporal)
                    ->searchable(['cliente_temporal']),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('total')
                    ->money('PEN')
                    ->sortable(),
                TextColumn::make('vendedor.nombres')
                    ->label('Vendedor')
                    ->formatStateUsing(fn (Venta $record) => $record->vendedor
                        ? "{$record->vendedor->nombres} {$record->vendedor->apellidos}"
                        : '—'),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(VentaEstado::class),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('cobrar')
                    ->label('Cobrar')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->modalHeading('Cobrar pre-venta')
                    ->modalSubmitActionLabel('Confirmar cobro')
                    ->visible(fn (Venta $record) => $record->estado === VentaEstado::Pendiente && Auth::user()?->can('cobrar', $record))
                    ->schema([
                        Select::make('metodo_pago')
                            ->label('Método de pago')
                            ->options(VentaMetodoPago::class)
                            ->live()
                            ->required(),
                        TextInput::make('referencia_pago')
                            ->label('Referencia de pago')
                            ->helperText('Obligatoria para transferencia, tarjeta o QR — conciliación con el banco.')
                            // $get() sobre un Select con options(EnumClass::class) devuelve la instancia
                            // del enum (EnumStateCast), no su ->value — comparar contra ->value aquí
                            // siempre da true (instancia !== string) y deja este campo requerido siempre.
                            ->required(fn (Get $get) => $get('metodo_pago') !== VentaMetodoPago::Efectivo)
                            ->maxLength(255),
                        TextInput::make('puntos_utilizados')
                            ->label('Puntos a canjear')
                            ->helperText(fn (Venta $record) => $record->cliente
                                ? "El cliente tiene {$record->cliente->puntos_acumulados} punto(s) disponibles. Pregúntale si desea canjearlos."
                                : 'Solo un cliente registrado puede canjear puntos.')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(fn (Venta $record) => $record->cliente?->puntos_acumulados ?? 0)
                            ->visible(fn (Venta $record) => $record->cliente_id !== null),
                    ])
                    ->action(function (array $data, Venta $record): void {
                        try {
                            // $data['metodo_pago'] ya llega como instancia de VentaMetodoPago (mismo
                            // EnumStateCast del Select), no como string — no hace falta ::from().
                            $resultado = (new CerrarVentaAction)->handle($record, [
                                'usuario_id' => Auth::id(),
                                'metodo_pago' => $data['metodo_pago'],
                                'referencia_pago' => $data['referencia_pago'] ?? null,
                                'puntos_utilizados' => (int) ($data['puntos_utilizados'] ?? 0),
                            ]);

                            $mensaje = $resultado->itemsRechazados === []
                                ? 'La venta se cobró correctamente.'
                                : count($resultado->itemsRechazados).' producto(s) sin stock suficiente quedaron fuera de la venta — revisa el detalle con el cliente.';

                            if ($resultado->venta->puntos_ganados > 0) {
                                $mensaje .= " El cliente ganó {$resultado->venta->puntos_ganados} punto(s).";
                            }

                            Notification::make()
                                ->title('Venta cerrada')
                                ->body($mensaje)
                                ->success()
                                ->send();
                        } catch (VentaSinStockDisponibleException|InvalidArgumentException|PuntosInsuficientesException $exception) {
                            Notification::make()
                                ->title('No se pudo cobrar la venta')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Venta $record) => $record->estado === VentaEstado::Pendiente && Auth::user()?->can('update', $record))
                    ->action(function (Venta $record): void {
                        try {
                            (new CancelarPreventaAction)->handle($record);

                            Notification::make()
                                ->title('Pre-venta cancelada')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('No se pudo cancelar')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
