<?php

declare(strict_types=1);

namespace App\Filament\Almacen\Resources\Compras\Pages;

use App\Actions\Compras\RegistrarCompraAction;
use App\Filament\Almacen\Resources\Compras\CompraResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    /**
     * Nunca confía en `almacen_id` del formulario a ciegas (mismo criterio que
     * `CreateVenta`, que deriva `sucursal_id` directamente de `Auth::user()`): si el
     * Almacenero tiene un solo almacén asignado, se usa ese directo sin depender del
     * campo (que además ya viene deshabilitado en el form); si tiene varios, se valida
     * que el elegido esté realmente entre los suyos antes de registrar la compra.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $almacenesPermitidos = Auth::user()->almacenes()->pluck('almacenes.id');

        $almacenId = $almacenesPermitidos->count() === 1
            ? $almacenesPermitidos->first()
            : (int) $data['almacen_id'];

        abort_unless($almacenesPermitidos->contains($almacenId), 403, 'No tienes ese almacén asignado.');

        return app(RegistrarCompraAction::class)->handle(
            proveedorId: (int) $data['proveedor_id'],
            almacenId: $almacenId,
            usuarioId: (int) Auth::id(),
            numeroFactura: $data['numero_factura'] ?? null,
            lineas: $data['detalles'],
        );
    }
}
