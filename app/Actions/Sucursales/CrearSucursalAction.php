<?php

declare(strict_types=1);

namespace App\Actions\Sucursales;

use App\Enums\AlmacenTipo;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;

class CrearSucursalAction
{
    /**
     * Crea una Sucursal junto con su Almacén inicial dentro de una misma transacción.
     * Es el único punto de entrada para crear sucursales: garantiza que ninguna
     * quede sin al menos un almacén asociado.
     *
     * @param  array{nombre: string, direccion?: string|null, estado?: bool}  $datosSucursal
     * @param  array{nombre: string, tipo: AlmacenTipo, estado?: bool}  $datosAlmacenInicial
     */
    public function handle(array $datosSucursal, array $datosAlmacenInicial): Sucursal
    {
        return DB::transaction(function () use ($datosSucursal, $datosAlmacenInicial) {
            $sucursal = Sucursal::create($datosSucursal);

            $sucursal->almacenes()->create($datosAlmacenInicial);

            return $sucursal;
        });
    }
}
