<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sucursals\Pages;

use App\Actions\Sucursales\CrearSucursalAction;
use App\Filament\Resources\Sucursals\SucursalResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSucursal extends CreateRecord
{
    protected static string $resource = SucursalResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return (new CrearSucursalAction())->handle(
            datosSucursal: [
                'nombre' => $data['nombre'],
                'direccion' => $data['direccion'] ?? null,
                'estado' => $data['estado'] ?? true,
            ],
            datosAlmacenInicial: [
                'nombre' => $data['almacen_nombre'],
                'tipo' => $data['almacen_tipo'],
            ],
        );
    }
}
