<?php

declare(strict_types=1);

namespace App\Filament\Resources\Almacens\Pages;

use App\Filament\Resources\Almacens\AlmacenResource;
use App\Filament\Support\AccionAjusteInventario;
use App\Filament\Support\SelectorProductoVariante;
use App\Models\Inventario;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Vista de stock de un almacén específico (Paso 3.8): reemplaza la
 * navegación "a ciegas" del ajuste manual (Paso 3.4) por un punto de
 * entrada contextual — se llega aquí desde la acción "Ver stock" de
 * `AlmacensTable`, y el botón "Registrar ajuste" ya trae el almacén fijado.
 */
class VerStockAlmacen extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = AlmacenResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->isAdmin() ?? false, 403);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return "Stock — {$this->getRecord()->sucursal->nombre} — {$this->getRecord()->nombre}";
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Inventario::query()
                ->where('almacen_id', $this->getRecord()->id)
                ->with(['productoVariante.producto.marca', 'productoVariante.producto.categoria']))
            ->recordUrl(null)
            ->recordClasses(fn (Inventario $record) => $record->cantidad <= $record->stock_minimo
                ? '[&>td]:bg-danger-50 dark:[&>td]:bg-danger-500/10'
                : null)
            ->columns([
                ImageColumn::make('productoVariante.producto.imagen_principal')
                    ->label('')
                    ->size(48)
                    ->square(),
                TextColumn::make('productoVariante.producto.nombre')
                    ->label('Producto')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Inventario $record) => SelectorProductoVariante::labelVariante($record->productoVariante)),
                TextColumn::make('productoVariante.producto.marca.nombre')
                    ->label('Marca')
                    ->toggleable(),
                TextColumn::make('productoVariante.producto.categoria.nombre')
                    ->label('Categoría')
                    ->toggleable(),
                TextColumn::make('cantidad')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn (Inventario $record) => $record->cantidad <= $record->stock_minimo ? 'danger' : 'success'),
                TextColumn::make('cantidad_comprometida')
                    ->label('Comprometido')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_minimo')
                    ->label('Mínimo')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('stock_bajo')
                    ->label('Stock bajo')
                    ->queries(
                        true: fn ($query) => $query->whereColumn('cantidad', '<=', 'stock_minimo'),
                        false: fn ($query) => $query->whereColumn('cantidad', '>', 'stock_minimo'),
                    ),
            ])
            ->defaultSort('cantidad');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            AccionAjusteInventario::make($this->getRecord()),
        ];
    }
}
