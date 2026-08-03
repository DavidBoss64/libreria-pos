<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Inventarios\Pages;

use App\Enums\AlmacenTipo;
use App\Filament\Pos\Resources\Inventarios\InventarioResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListInventarios extends ListRecords
{
    protected static string $resource = InventarioResource::class;

    /**
     * "Mi sucursal" (stock propio, comportamiento sin cambios) y "Almacenes (traspaso)"
     * (depósitos disponibles como origen de traspaso, ver InventarioResource::scopeQueryToSucursal).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'mi_sucursal' => Tab::make('Mi sucursal')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'almacen',
                    fn (Builder $q) => $q->where('sucursal_id', Auth::user()?->sucursal_id),
                )),
            'almacenes' => Tab::make('Almacenes (traspaso)')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'almacen',
                    fn (Builder $q) => $q->where('tipo', AlmacenTipo::Deposito)->where('estado', true),
                )),
        ];
    }
}
