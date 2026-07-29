<?php

declare(strict_types=1);

namespace App\Actions\Traspasos;

use App\Enums\TraspasoEstado;
use App\Models\Traspaso;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SolicitarTraspasoAction
{
    /**
     * Crea la solicitud de traspaso (estado inicial 'solicitado') junto con sus líneas,
     * dentro de una misma transacción. No mueve stock todavía — eso solo ocurre al
     * completarse (ver CompletarTraspasoAction).
     *
     * @param  array{almacen_origen_id: int, almacen_destino_id: int, usuario_solicitante_id: int, detalles: list<array{producto_variante_id: int, cantidad: int}>}  $datos
     */
    public function handle(array $datos): Traspaso
    {
        if ($datos['detalles'] === []) {
            throw new InvalidArgumentException('Un traspaso debe solicitarse con al menos un producto.');
        }

        return DB::transaction(function () use ($datos) {
            $traspaso = Traspaso::create([
                'almacen_origen_id' => $datos['almacen_origen_id'],
                'almacen_destino_id' => $datos['almacen_destino_id'],
                'usuario_solicitante_id' => $datos['usuario_solicitante_id'],
                'estado' => TraspasoEstado::Solicitado,
            ]);

            foreach ($datos['detalles'] as $detalle) {
                $traspaso->detalles()->create($detalle);
            }

            return $traspaso->load('detalles');
        });
    }
}
