<?php

declare(strict_types=1);

namespace App\Filament\Pos\Resources\Traspasos\Pages;

use App\Actions\Traspasos\SolicitarTraspasoAction;
use App\Filament\Pos\Resources\Traspasos\TraspasoResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateTraspaso extends CreateRecord
{
    protected static string $resource = TraspasoResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return (new SolicitarTraspasoAction())->handle([
            'almacen_origen_id' => (int) $data['almacen_origen_id'],
            'almacen_destino_id' => (int) $data['almacen_destino_id'],
            'usuario_solicitante_id' => Auth::id(),
            'detalles' => $data['detalles'],
        ]);
    }
}
