<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sucursals\Pages;

use App\Filament\Resources\Sucursals\SucursalResource;
use App\Models\Sucursal;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateSucursal extends CreateRecord
{
    protected static string $resource = SucursalResource::class;

    /**
     * Envuelve la creación en una transacción para que la Sucursal y el Almacén
     * `tienda` que crea SucursalObserver::created() sean atómicos: si el Observer
     * falla, la Sucursal tampoco queda persistida.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(fn () => Sucursal::create($data));
    }
}
