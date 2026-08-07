<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Actions\Compras\AnularCompraAction;
use App\Enums\CompraEstado;
use App\Exceptions\StockInsuficienteException;
use App\Models\Compra;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Tabla de Compras compartida entre el panel `admin` y el panel `almacen` — mismo
 * criterio que `CompraForm`. La acción "Anular" queda oculta sola para quien no tenga
 * la habilidad `anular` de `CompraPolicy` (solo `admin`, ver decisión Paso 5.2).
 */
class CompraTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('proveedor.razon_social')
                    ->label('Proveedor')
                    ->searchable(),
                TextColumn::make('almacen_mostrado')
                    ->label('Almacén')
                    ->state(fn (Compra $record) => "{$record->almacen->sucursal->nombre} — {$record->almacen->nombre}"),
                TextColumn::make('numero_factura')
                    ->label('Factura')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('total')
                    ->money('PEN')
                    ->sortable(),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('usuario.nombres')
                    ->label('Registrada por')
                    ->formatStateUsing(fn (Compra $record) => "{$record->usuario->nombres} {$record->usuario->apellidos}"),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(CompraEstado::class),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('anular')
                    ->label('Anular')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Esto revierte el stock que esta compra ingresó. Si parte de ese stock ya se vendió o se traspasó, no se podrá anular.')
                    ->visible(fn (Compra $record) => $record->estado === CompraEstado::Completado && Auth::user()?->can('anular', $record))
                    ->action(function (Compra $record): void {
                        try {
                            app(AnularCompraAction::class)->handle($record, (int) Auth::id());

                            Notification::make()
                                ->title('Compra anulada')
                                ->success()
                                ->send();
                        } catch (StockInsuficienteException|RuntimeException $exception) {
                            Notification::make()
                                ->title('No se pudo anular la compra')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
