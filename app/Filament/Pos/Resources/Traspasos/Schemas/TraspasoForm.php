<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Traspasos\Schemas;

use App\Enums\AlmacenTipo;
use App\Models\Almacen;
use App\Models\ProductoVariante;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TraspasoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Traspaso')
                    ->columns(2)
                    ->components([
                        Select::make('almacen_origen_id')
                            ->label('Almacén origen (depósito)')
                            ->options(fn () => Almacen::query()
                                ->where('tipo', AlmacenTipo::Deposito)
                                ->pluck('nombre', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('almacen_destino_id')
                            ->label('Almacén destino (mi sucursal)')
                            ->options(fn () => Almacen::query()
                                ->where('sucursal_id', Auth::user()?->sucursal_id)
                                ->pluck('nombre', 'id'))
                            ->searchable()
                            ->required(),
                    ]),
                Section::make('Productos solicitados')
                    ->components([
                        Repeater::make('detalles')
                            ->hiddenLabel()
                            ->schema([
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
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Agregar otro producto')
                            ->reorderable(false),
                    ]),
            ]);
    }
}
