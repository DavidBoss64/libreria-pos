<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdelantosSueldo\Pages;

use App\Enums\AdelantoSueldoEstado;
use App\Filament\Resources\AdelantosSueldo\AdelantoSueldoResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAdelantoSueldo extends CreateRecord
{
    protected static string $resource = AdelantoSueldoResource::class;

    /**
     * `registrado_por_id` siempre es el admin autenticado (nunca un valor del
     * formulario) y todo adelanto nuevo nace `pendiente_descuento` — mismo criterio
     * que `CreateVenta` derivando `sucursal_id`/`vendedor_id` de `Auth::user()`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registrado_por_id'] = Auth::id();
        $data['estado'] = AdelantoSueldoEstado::PendienteDescuento;

        return $data;
    }
}
