<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categorias\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create'
                        ? $set('slug', Str::slug($state ?? ''))
                        : null),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(150)
                    ->unique(ignoreRecord: true),
            ]);
    }
}
