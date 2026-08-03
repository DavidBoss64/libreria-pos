<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Inventarios;

use App\Enums\AlmacenTipo;
use App\Filament\Concerns\AislaPorSucursal;
use App\Filament\Pos\Resources\Inventarios\Pages\ListInventarios;
use App\Filament\Pos\Resources\Inventarios\Tables\InventariosTable;
use App\Models\Inventario;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventarioResource extends Resource
{
    use AislaPorSucursal;

    protected static ?string $model = Inventario::class;

    protected static ?string $modelLabel = 'Stock';

    protected static ?string $pluralModelLabel = 'Stock';

    protected static ?string $navigationLabel = 'Stock';

    protected static ?string $slug = 'inventarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    /**
     * Base de visibilidad del Vendedor/Cajero: su propio stock (almacenes de su sucursal)
     * MÁS el stock de los almacenes tipo depósito (cualquier sucursal dueña) — son los
     * únicos orígenes válidos de traspaso hoy (ver `TraspasoForm`), así que el Vendedor
     * necesita poder consultarlos para decidir de dónde pedir. Las pestañas de
     * `ListInventarios` recortan esta base en "Mi sucursal" / "Almacenes (traspaso)".
     * Deliberadamente NO incluye tiendas de otras sucursales — no existe un flujo de
     * traspaso tienda-a-tienda que use ese dato, y exponerlo rompería el aislamiento por
     * sucursal (LOGICA_NEGOCIO.md sección 1) sin ningún propósito operativo.
     */
    protected static function scopeQueryToSucursal(Builder $query, ?int $sucursalId): Builder
    {
        return $query->whereHas('almacen', fn ($q) => $q
            ->where('sucursal_id', $sucursalId)
            ->orWhere(fn ($q2) => $q2
                ->where('tipo', AlmacenTipo::Deposito)
                ->where('estado', true)));
    }

    public static function table(Table $table): Table
    {
        return InventariosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventarios::route('/'),
        ];
    }
}
