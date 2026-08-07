<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdelantosSueldo\Tables;

use App\Enums\AdelantoSueldoEstado;
use App\Models\AdelantoSueldo;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdelantosSueldoTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('usuario.nombres')
                    ->label('Empleado')
                    ->formatStateUsing(fn (AdelantoSueldo $record) => "{$record->usuario->nombres} {$record->usuario->apellidos}")
                    ->searchable(['nombres', 'apellidos']),
                TextColumn::make('monto')
                    ->money('PEN')
                    ->sortable(),
                TextColumn::make('fecha_adelanto')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                TextColumn::make('estado')
                    ->badge()
                    ->sortable(),
                TextColumn::make('registradoPor.nombres')
                    ->label('Registrado por')
                    ->formatStateUsing(fn (AdelantoSueldo $record) => "{$record->registradoPor->nombres} {$record->registradoPor->apellidos}")
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(AdelantoSueldoEstado::class),
            ])
            ->recordActions([
                Action::make('marcarDescontado')
                    ->label('Marcar como descontado')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AdelantoSueldo $record) => $record->estado === AdelantoSueldoEstado::PendienteDescuento)
                    ->action(function (AdelantoSueldo $record) {
                        $record->update(['estado' => AdelantoSueldoEstado::Descontado]);

                        Notification::make()
                            ->title('Adelanto marcado como descontado')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
