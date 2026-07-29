<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn(string $operation, ?string $state, Set $set) => $operation === 'create'
                        ? $set('slug', Str::slug($state ?? ''))
                        : null),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('marca_id')
                    ->label('Marca')
                    ->relationship('marca', 'nombre')
                    ->searchable()
                    ->preload(),
                Select::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('imagen_principal')
                    ->label('Imagen (URL)')
                    ->url()
                    ->maxLength(255)
                    ->live(debounce: '500ms'),
                TextEntry::make('imagen_preview')
                    ->label('Vista previa')
                    ->html()
                    ->state(function (Get $get) {
                        $url = $get('imagen_principal');

                        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                            return 'Sin imagen o URL inválida.';
                        }

                        return new HtmlString(
                            '<img src="' . e($url) . '" style="max-height:200px;max-width:100%;border-radius:0.5rem;object-fit:contain;" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'block\'">'
                                . '<span style="display:none;color:rgb(239 68 68);">No se pudo cargar la imagen desde esa URL.</span>'
                        );
                    }),
                Toggle::make('estado')
                    ->default(true)
                    ->required(),
            ]);
    }
}
