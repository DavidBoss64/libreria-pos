<?php

declare(strict_types=1);

namespace App\Filament\Resources\Compras\Pages;

use App\Actions\Compras\RegistrarCompraAction;
use App\Filament\Resources\Compras\CompraResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(RegistrarCompraAction::class)->handle(
            proveedorId: (int) $data['proveedor_id'],
            almacenId: (int) $data['almacen_id'],
            usuarioId: (int) Auth::id(),
            numeroFactura: $data['numero_factura'] ?? null,
            lineas: $data['detalles'],
        );
    }
}
