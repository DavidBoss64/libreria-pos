<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Rules\SucursalRequeridaSegunRol;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombres')
                    ->required()
                    ->maxLength(150),
                TextInput::make('apellidos')
                    ->required()
                    ->maxLength(150),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
                Select::make('role')
                    ->label('Rol')
                    ->options(UserRole::class)
                    ->required()
                    ->live(),
                Select::make('sucursal_id')
                    ->label('Sucursal')
                    ->relationship('sucursal', 'nombre')
                    ->searchable()
                    ->preload()
                    ->rule(function (Get $get): SucursalRequeridaSegunRol {
                        $role = $get('role');

                        return new SucursalRequeridaSegunRol(
                            $role instanceof UserRole ? $role : UserRole::tryFrom($role ?? '')
                        );
                    }),
                Select::make('almacenes')
                    ->label('Almacenes asignados')
                    ->relationship('almacenes', 'nombre')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Solo aplica a Almaceneros. Normalmente se asignan almacenes de tipo Depósito, no Tienda — el Almacenero gestiona stock bruto, no el punto de venta.'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->required(),
            ]);
    }
}
