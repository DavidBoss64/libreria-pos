<?php

declare(strict_types=1);

namespace App\Actions\Puntos;

use App\Enums\TipoMovimientoPuntos;
use App\Exceptions\PuntosInsuficientesException;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrarMovimientoPuntosAction
{
    /**
     * Único punto de entrada para modificar `clientes.puntos_acumulados`: nunca se debe
     * hacer `Cliente::update()` fuera de esta clase (Kardex obligatorio, ver CLAUDE.md
     * regla 5 — mismo principio que `RegistrarMovimientoInventarioAction`).
     *
     * Para 'ganado'/'canjeado', $puntos es siempre positivo y la dirección la determina
     * $tipo. Para 'ajuste', $puntos es un delta con signo (puede subir o bajar el saldo).
     */
    public function handle(
        int $clienteId,
        TipoMovimientoPuntos $tipo,
        int $puntos,
        ?int $ventaId = null,
        ?int $usuarioId = null,
    ): MovimientoPuntos {
        $delta = match ($tipo) {
            TipoMovimientoPuntos::Ganado => $this->puntosPositivosOFalla($puntos),
            TipoMovimientoPuntos::Canjeado => -$this->puntosPositivosOFalla($puntos),
            TipoMovimientoPuntos::Ajuste => $this->puntosDistintosDeCeroOFalla($puntos),
        };

        return DB::transaction(function () use ($clienteId, $tipo, $delta, $ventaId, $usuarioId) {
            $cliente = Cliente::where('id', $clienteId)->lockForUpdate()->firstOrFail();

            $saldoDespues = $cliente->puntos_acumulados + $delta;

            if ($saldoDespues < 0) {
                throw new PuntosInsuficientesException($clienteId, $cliente->puntos_acumulados, abs($delta));
            }

            $cliente->update(['puntos_acumulados' => $saldoDespues]);

            return MovimientoPuntos::create([
                'cliente_id' => $clienteId,
                'venta_id' => $ventaId,
                'tipo' => $tipo,
                'puntos' => $delta,
                'saldo_despues' => $saldoDespues,
                'usuario_id' => $usuarioId,
            ]);
        });
    }

    private function puntosPositivosOFalla(int $puntos): int
    {
        if ($puntos <= 0) {
            throw new InvalidArgumentException('La cantidad de puntos ganados/canjeados debe ser mayor a cero.');
        }

        return $puntos;
    }

    private function puntosDistintosDeCeroOFalla(int $puntos): int
    {
        if ($puntos === 0) {
            throw new InvalidArgumentException('La cantidad de puntos de un ajuste no puede ser cero.');
        }

        return $puntos;
    }
}
