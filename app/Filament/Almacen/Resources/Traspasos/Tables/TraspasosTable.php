<?php

declare(strict_types=1);

namespace App\Filament\Almacen\Resources\Traspasos\Tables;

use App\Actions\Traspasos\CompletarTraspasoAction;
use App\Enums\TraspasoEstado;
use App\Exceptions\StockInsuficienteException;
use App\Models\Traspaso;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TraspasosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('almacenOrigen.nombre')
                    ->label('Origen')
                    ->sortable(),
                TextColumn::make('almacenDestino.nombre')
                    ->label('Destino')
                    ->sortable(),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('detalles_count')
                    ->label('Productos')
                    ->counts('detalles')
                    ->alignCenter(),
                TextColumn::make('usuarioSolicitante.nombres')
                    ->label('Solicitante')
                    ->formatStateUsing(fn ($record) => "{$record->usuarioSolicitante->nombres} {$record->usuarioSolicitante->apellidos}"),
                TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(TraspasoEstado::class),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('marcarPreparando')
                    ->label('Preparar')
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color('warning')
                    ->visible(fn (Traspaso $record) => $record->estado === TraspasoEstado::Solicitado && Auth::user()?->can('update', $record))
                    ->action(fn (Traspaso $record) => $record->update(['estado' => TraspasoEstado::Preparando])),
                Action::make('marcarEnTransito')
                    ->label('Despachar')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('info')
                    ->visible(fn (Traspaso $record) => $record->estado === TraspasoEstado::Preparando && Auth::user()?->can('update', $record))
                    ->action(fn (Traspaso $record) => $record->update(['estado' => TraspasoEstado::EnTransito])),
                Action::make('completar')
                    ->label('Completar')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Traspaso $record) => $record->estado === TraspasoEstado::EnTransito && Auth::user()?->can('update', $record))
                    ->action(function (Traspaso $record) {
                        try {
                            (new CompletarTraspasoAction())->handle($record, Auth::id());

                            Notification::make()
                                ->title('Traspaso completado')
                                ->body('El stock se movió del almacén origen al destino.')
                                ->success()
                                ->send();
                        } catch (StockInsuficienteException $exception) {
                            Notification::make()
                                ->title('No se pudo completar el traspaso')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon(Heroicon::OutlinedArchiveBoxXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Traspaso $record) => in_array($record->estado, [
                        TraspasoEstado::Solicitado,
                        TraspasoEstado::Preparando,
                        TraspasoEstado::EnTransito,
                    ], true) && Auth::user()?->can('update', $record))
                    ->action(fn (Traspaso $record) => $record->update(['estado' => TraspasoEstado::Cancelado])),
            ]);
    }
}
