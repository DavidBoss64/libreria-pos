<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdelantosSueldo\Schemas;

use App\Enums\AdelantoSueldoEstado;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdelantoSueldoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('usuario_id')
                    ->label('Empleado')
                    ->options(fn () => User::query()
                        ->where('is_active', true)
                        ->orderBy('nombres')
                        ->get()
                        ->mapWithKeys(fn (User $user) => [
                            $user->id => "{$user->nombres} {$user->apellidos} ({$user->role->getLabel()})",
                        ]))
                    ->searchable()
                    ->required(),
                TextInput::make('monto')
                    ->label('Monto')
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('S/')
                    ->required(),
                DatePicker::make('fecha_adelanto')
                    ->label('Fecha del adelanto')
                    ->default(now())
                    ->native(false)
                    ->required(),
                Select::make('estado')
                    ->label('Estado')
                    ->options(AdelantoSueldoEstado::class)
                    ->default(AdelantoSueldoEstado::PendienteDescuento)
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Se cambia desde la acción "Marcar como descontado" en la tabla, no aquí.')
                    ->visibleOn('edit'),
            ]);
    }
}
