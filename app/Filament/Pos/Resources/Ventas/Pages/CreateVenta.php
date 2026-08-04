<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Ventas\Pages;

use App\Actions\Ventas\CrearPreventaAction;
use App\Filament\Pos\Resources\Ventas\VentaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return (new CrearPreventaAction())->handle([
            'sucursal_id' => Auth::user()->sucursal_id,
            'vendedor_id' => Auth::id(),
            'cliente_id' => $data['cliente_id'] ?? null,
            'cliente_temporal' => $data['cliente_temporal'] ?? null,
            'detalles' => collect($data['detalles'])->map(fn (array $linea): array => [
                'producto_variante_id' => (int) $linea['producto_variante_id'],
                'cantidad' => (int) $linea['cantidad'],
                'forzar_mayorista' => (bool) ($linea['forzar_mayorista'] ?? false),
            ])->all(),
        ]);
    }
}
