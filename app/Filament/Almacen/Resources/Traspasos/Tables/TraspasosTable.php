<?php

declare(strict_types=1);

namespace App\Filament\Almacen\Resources\Traspasos\Tables;

use App\Actions\Traspasos\CompletarTraspasoAction;
use App\Actions\Traspasos\TransicionarTraspasoAEnTransitoAction;
use App\Enums\TraspasoEstado;
use App\Exceptions\PreparacionIncompletaException;
use App\Exceptions\StockInsuficienteException;
use App\Models\Inventario;
use App\Models\ProductoVariante;
use App\Models\Traspaso;
use App\Models\TraspasoDetalle;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
                Action::make('registrarPreparacion')
                    ->label('Registrar preparación')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('warning')
                    ->modalHeading('Registrar cantidades preparadas')
                    ->modalDescription('El "Disponible" es el stock actual en el almacén origen al momento de abrir este formulario; puede cambiar si hay otros movimientos en paralelo.')
                    ->modalSubmitActionLabel('Guardar')
                    ->modalWidth('3xl')
                    ->visible(fn (Traspaso $record) => $record->estado === TraspasoEstado::Preparando && Auth::user()?->can('update', $record))
                    ->schema([
                        Repeater::make('detalles')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('id'),
                                Hidden::make('disponible'),
                                Hidden::make('producto_html'),
                                TextEntry::make('producto_preview')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn (Get $get) => $get('producto_html'))
                                    ->columnSpanFull(),
                                TextInput::make('cantidad')
                                    ->label('Solicitado')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('cantidad_preparada')
                                    ->label('Preparado')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(fn (Get $get) => min((int) $get('cantidad'), (int) $get('disponible')))
                                    ->required()
                                    ->hint(fn (Get $get) => "Disponible en origen: {$get('disponible')}")
                                    ->hintColor(fn (Get $get) => ((int) $get('disponible')) >= ((int) $get('cantidad')) ? 'success' : 'danger')
                                    ->hintIcon(fn (Get $get) => ((int) $get('disponible')) >= ((int) $get('cantidad')) ? null : Heroicon::OutlinedExclamationTriangle),
                            ])
                            ->columns(2)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ])
                    ->fillForm(fn (Traspaso $record): array => static::datosParaPreparacion($record))
                    ->action(function (array $data, Traspaso $record): void {
                        foreach ($data['detalles'] as $detalleData) {
                            $record->detalles()
                                ->where('id', $detalleData['id'])
                                ->update(['cantidad_preparada' => (int) $detalleData['cantidad_preparada']]);
                        }

                        Notification::make()
                            ->title('Preparación registrada')
                            ->success()
                            ->send();
                    }),
                Action::make('marcarEnTransito')
                    ->label('Despachar')
                    ->icon(Heroicon::OutlinedTruck)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Traspaso $record) => $record->estado === TraspasoEstado::Preparando && Auth::user()?->can('update', $record))
                    ->action(function (Traspaso $record) {
                        try {
                            (new TransicionarTraspasoAEnTransitoAction())->handle($record);

                            Notification::make()
                                ->title('Traspaso despachado')
                                ->body('El traspaso quedó en tránsito.')
                                ->success()
                                ->send();
                        } catch (PreparacionIncompletaException $exception) {
                            Notification::make()
                                ->title('No se pudo despachar el traspaso')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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

    /**
     * @return array{detalles: list<array<string, mixed>>}
     */
    protected static function datosParaPreparacion(Traspaso $record): array
    {
        $disponiblePorVariante = Inventario::query()
            ->where('almacen_id', $record->almacen_origen_id)
            ->whereIn('producto_variante_id', $record->detalles->pluck('producto_variante_id'))
            ->pluck('cantidad', 'producto_variante_id');

        return [
            'detalles' => $record->detalles->map(fn (TraspasoDetalle $detalle) => [
                'id' => $detalle->id,
                'producto_html' => (string) static::renderPreviewVariante($detalle->productoVariante),
                'cantidad' => $detalle->cantidad,
                'cantidad_preparada' => $detalle->cantidad_preparada,
                'disponible' => $disponiblePorVariante->get($detalle->producto_variante_id, 0),
            ])->all(),
        ];
    }

    protected static function renderPreviewVariante(ProductoVariante $variante): HtmlString
    {
        $producto = $variante->producto;

        $imagenHtml = (filled($producto->imagen_principal) && filter_var($producto->imagen_principal, FILTER_VALIDATE_URL))
            ? '<img src="' . e($producto->imagen_principal) . '" style="height:48px;width:48px;object-fit:cover;border-radius:0.5rem;flex-shrink:0;" onerror="this.style.display=\'none\'">'
            : '<div style="height:48px;width:48px;border-radius:0.5rem;background:rgb(228 228 231 / 0.5);flex-shrink:0;"></div>';

        $atributos = collect($variante->atributos ?? [])
            ->map(fn ($valor, $clave) => Str::title((string) $clave) . ': ' . $valor)
            ->implode(', ');

        $badges = collect([
            $producto->marca?->nombre,
            $producto->categoria?->nombre,
            $atributos !== '' ? $atributos : null,
            $variante->codigo_interno,
        ])->filter()
            ->map(fn (string $texto) => '<span style="display:inline-block;padding:0.125rem 0.5rem;margin-right:0.25rem;border-radius:9999px;background:rgb(228 228 231 / 0.7);font-size:0.75rem;">' . e($texto) . '</span>')
            ->implode('');

        return new HtmlString(
            '<div style="display:flex;align-items:center;gap:0.75rem;">'
                . $imagenHtml
                . '<div><div style="font-weight:600;">' . e($producto->nombre) . '</div><div style="margin-top:0.25rem;">' . $badges . '</div></div>'
                . '</div>'
        );
    }
}
