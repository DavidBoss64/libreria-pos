<?php

declare(strict_types=1);

namespace App\Actions\Sucursales;

use App\Enums\AlmacenTipo;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CrearSucursalAction
{
    /**
     * Crea una Sucursal junto con sus Almacenes iniciales dentro de una misma transacción.
     * Es el único punto de entrada para crear sucursales: garantiza que ninguna
     * quede sin al menos un almacén asociado.
     *
     * @param  array{nombre: string, direccion?: string|null, estado?: bool}  $datosSucursal
     * @param  list<array{nombre: string, tipo: AlmacenTipo}>  $datosAlmacenes
     */
    public function handle(array $datosSucursal, array $datosAlmacenes): Sucursal
    {
        if ($datosAlmacenes === []) {
            throw new InvalidArgumentException('Una Sucursal debe crearse con al menos un Almacén.');
        }

        return DB::transaction(function () use ($datosSucursal, $datosAlmacenes) {
            $sucursal = Sucursal::create($datosSucursal);

            foreach ($datosAlmacenes as $datosAlmacen) {
                $sucursal->almacenes()->create($datosAlmacen);
            }

            return $sucursal;
        });
    }
}
