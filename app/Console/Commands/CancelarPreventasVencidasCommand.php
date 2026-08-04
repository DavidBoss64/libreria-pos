<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Ventas\CancelarPreventaAction;
use App\Enums\VentaEstado;
use App\Models\Venta;
use Illuminate\Console\Command;

class CancelarPreventasVencidasCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ventas:cancelar-preventas-vencidas';

    /**
     * @var string
     */
    protected $description = "Cancela ('anulado') las pre-ventas 'pendiente' cuyo expira_en ya pasó, liberando su cantidad_comprometida (ver LOGICA_NEGOCIO.md sección 3, 'Manejo de pre-ventas abandonadas').";

    public function handle(CancelarPreventaAction $cancelarPreventa): int
    {
        $vencidas = Venta::where('estado', VentaEstado::Pendiente)
            ->whereNotNull('expira_en')
            ->where('expira_en', '<', now())
            ->get();

        foreach ($vencidas as $venta) {
            $cancelarPreventa->handle($venta);
        }

        $this->info("Pre-ventas vencidas canceladas: {$vencidas->count()}.");

        return self::SUCCESS;
    }
}
