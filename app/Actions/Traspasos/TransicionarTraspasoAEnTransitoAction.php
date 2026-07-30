<?php

declare(strict_types=1);

namespace App\Actions\Traspasos;

use App\Enums\TraspasoEstado;
use App\Exceptions\PreparacionIncompletaException;
use App\Models\Traspaso;
use RuntimeException;

class TransicionarTraspasoAEnTransitoAction
{
    /**
     * Pasa un Traspaso de 'preparando' a 'en_transito'. Bloquea la transición si
     * alguna línea todavía no tiene cantidad_preparada registrada (aunque sea 0),
     * para evitar despachar cantidades no verificadas por el Almacenero.
     */
    public function handle(Traspaso $traspaso): Traspaso
    {
        if ($traspaso->estado !== TraspasoEstado::Preparando) {
            throw new RuntimeException("Solo se puede despachar un traspaso en estado 'preparando'.");
        }

        if ($traspaso->detalles()->whereNull('cantidad_preparada')->exists()) {
            throw new PreparacionIncompletaException($traspaso->id);
        }

        $traspaso->update(['estado' => TraspasoEstado::EnTransito]);

        return $traspaso->fresh(['detalles']);
    }
}
