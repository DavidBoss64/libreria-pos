<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoPrecioAplicado;
use App\Models\ProductoVariante;
use InvalidArgumentException;
use RuntimeException;

class PrecioService
{
    private const UMBRAL_DOCENA = 12;

    public function calcularPrecio(int $productoVarianteId, int $cantidad, bool $forzarMayorista = false): PrecioCalculado
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad debe ser mayor a 0, recibido: {$cantidad}.");
        }

        $umbralMayor = (int) config('precios.umbral_mayor');

        if ($umbralMayor <= self::UMBRAL_DOCENA) {
            throw new RuntimeException(
                'config(precios.umbral_mayor) debe ser mayor al umbral de Docena ('.self::UMBRAL_DOCENA.'), '
                ."valor actual: {$umbralMayor}."
            );
        }

        $variante = ProductoVariante::findOrFail($productoVarianteId);

        $tipo = $this->determinarTipoPrecio($cantidad, $forzarMayorista, $umbralMayor);
        $precioUnitario = $this->precioUnitarioPara($variante, $tipo);
        $subtotal = bcmul($precioUnitario, (string) $cantidad, 2);

        return new PrecioCalculado($tipo, $precioUnitario, $subtotal);
    }

    private function determinarTipoPrecio(int $cantidad, bool $forzarMayorista, int $umbralMayor): TipoPrecioAplicado
    {
        if ($forzarMayorista) {
            return TipoPrecioAplicado::Mayor;
        }

        if ($cantidad >= $umbralMayor) {
            return TipoPrecioAplicado::Mayor;
        }

        if ($cantidad >= self::UMBRAL_DOCENA) {
            return TipoPrecioAplicado::Docena;
        }

        return TipoPrecioAplicado::Unidad;
    }

    private function precioUnitarioPara(ProductoVariante $variante, TipoPrecioAplicado $tipo): string
    {
        return match ($tipo) {
            TipoPrecioAplicado::Unidad => (string) $variante->precio_venta_unidad,
            TipoPrecioAplicado::Docena => (string) $variante->precio_venta_docena,
            TipoPrecioAplicado::Mayor => (string) $variante->precio_venta_mayor,
        };
    }
}
