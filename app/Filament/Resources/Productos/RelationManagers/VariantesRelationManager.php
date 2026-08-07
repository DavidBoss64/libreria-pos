<?php

declare(strict_types=1);

namespace App\Filament\Resources\Productos\RelationManagers;

use App\Filament\Support\AccionesPapelera;
use App\Models\ProductoVariante;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class VariantesRelationManager extends RelationManager
{
    protected static string $relationship = 'variantes';

    protected static ?string $title = 'Variantes';

    /**
     * Calcula el margen de ganancia (%) frente al costo. Devuelve null (no un error
     * ni una división por cero) cuando el costo no es un número válido o es 0.
     */
    private static function calcularMargen(mixed $costo, mixed $precio): ?string
    {
        if (! is_numeric($costo) || (float) $costo === 0.0 || ! is_numeric($precio)) {
            return null;
        }

        return number_format((((float) $precio - (float) $costo) / (float) $costo) * 100, 2, '.', '');
    }

    /**
     * Inverso de calcularMargen: sugiere el precio a partir del margen deseado.
     * También devuelve null si el costo no es válido, para no pisar el precio actual.
     */
    private static function calcularPrecioDesdeMargen(mixed $costo, mixed $margen): ?string
    {
        if (! is_numeric($costo) || (float) $costo === 0.0 || ! is_numeric($margen)) {
            return null;
        }

        return number_format((float) $costo * (1 + ((float) $margen / 100)), 2, '.', '');
    }

    private static function campoPrecio(string $nombrePrecio, string $nombreMargen, string $label): array
    {
        return [
            TextInput::make($nombrePrecio)
                ->label($label)
                ->numeric()
                ->required()
                ->prefix('S/')
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => $set(
                    $nombreMargen,
                    self::calcularMargen($get('costo_real'), $get($nombrePrecio))
                )),
            TextInput::make($nombreMargen)
                ->label('Margen %')
                ->numeric()
                ->suffix('%')
                ->placeholder('N/A')
                ->dehydrated(false)
                ->live(onBlur: true)
                ->afterStateHydrated(function (TextInput $component, ?ProductoVariante $record) use ($nombrePrecio) {
                    if ($record !== null) {
                        $component->state(self::calcularMargen($record->costo_real, $record->{$nombrePrecio}));
                    }
                })
                ->afterStateUpdated(function (Set $set, Get $get) use ($nombrePrecio, $nombreMargen) {
                    $precioSugerido = self::calcularPrecioDesdeMargen($get('costo_real'), $get($nombreMargen));

                    if ($precioSugerido !== null) {
                        $set($nombrePrecio, $precioSugerido);
                    }
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codigo_interno')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('codigo_barras')
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                KeyValue::make('atributos')
                    ->keyLabel('Atributo')
                    ->valueLabel('Valor')
                    ->reorderable(false),
                TextInput::make('unidades_por_caja')
                    ->label('Unidades por caja')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->nullable()
                    ->helperText('Opcional. Si se define, el ajuste manual de inventario permite ingresar stock por caja además de por unidad.'),
                TextInput::make('costo_real')
                    ->label('Costo real')
                    ->numeric()
                    ->required()
                    ->prefix('S/')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        $set('margen_unidad', self::calcularMargen($get('costo_real'), $get('precio_venta_unidad')));
                        $set('margen_docena', self::calcularMargen($get('costo_real'), $get('precio_venta_docena')));
                        $set('margen_mayor', self::calcularMargen($get('costo_real'), $get('precio_venta_mayor')));
                    }),
                Section::make('Precio Unidad')
                    ->columns(2)
                    ->components(self::campoPrecio('precio_venta_unidad', 'margen_unidad', 'Precio Unidad')),
                Section::make('Precio Docena')
                    ->columns(2)
                    ->components(self::campoPrecio('precio_venta_docena', 'margen_docena', 'Precio Docena')),
                Section::make('Precio Mayorista')
                    ->columns(2)
                    ->components(self::campoPrecio('precio_venta_mayor', 'margen_mayor', 'Precio Mayorista')),
                Toggle::make('estado')
                    ->default(true)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('codigo_interno')
            ->columns([
                TextColumn::make('codigo_interno')
                    ->searchable(),
                TextColumn::make('codigo_barras')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unidades_por_caja')
                    ->label('Unid./caja')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('costo_real')
                    ->money('PEN')
                    ->sortable(),
                TextColumn::make('precio_venta_unidad')
                    ->label('Unidad')
                    ->money('PEN')
                    ->sortable(),
                TextColumn::make('precio_venta_docena')
                    ->label('Docena')
                    ->money('PEN')
                    ->sortable(),
                TextColumn::make('precio_venta_mayor')
                    ->label('Mayor')
                    ->money('PEN')
                    ->sortable(),
                IconColumn::make('estado')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                AccionesPapelera::forceDeleteSeguro(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
