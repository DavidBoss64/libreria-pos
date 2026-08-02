<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Producto;
use App\Models\ProductoVariante;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Cascada Producto → Variante reutilizable (Paso 3.6 y 3.8): un `Select`
 * transitorio de Producto con preview (imagen/marca/categoría) que filtra
 * un segundo `Select` de Variante, con label armado desde `atributos`
 * (color/medida/etc.) en vez del `codigo_interno` a secas. Se usa tanto en
 * `TraspasoForm` (panel `pos`) como en el ajuste de inventario (panel
 * `admin`), para no tener dos criterios distintos de "qué variante es esta".
 */
class SelectorProductoVariante
{
    public static function renderPreviewProducto(mixed $productoId): HtmlString|string
    {
        if (blank($productoId)) {
            return 'Selecciona un producto para ver su detalle.';
        }

        $producto = Producto::query()->with(['marca', 'categoria'])->find($productoId);

        if (! $producto) {
            return 'Selecciona un producto para ver su detalle.';
        }

        $imagenHtml = (filled($producto->imagen_principal) && filter_var($producto->imagen_principal, FILTER_VALIDATE_URL))
            ? '<img src="' . e($producto->imagen_principal) . '" style="height:56px;width:56px;object-fit:cover;border-radius:0.5rem;flex-shrink:0;" onerror="this.style.display=\'none\'">'
            : '<div style="height:56px;width:56px;border-radius:0.5rem;background:rgb(228 228 231 / 0.5);flex-shrink:0;"></div>';

        $badges = collect([
            $producto->marca?->nombre,
            $producto->categoria?->nombre,
        ])->filter()
            ->map(fn(string $texto) => '<span style="display:inline-block;padding:0.125rem 0.5rem;margin-right:0.25rem;border-radius:9999px;background:rgb(228 228 231 / 0.7);font-size:0.75rem;">' . e($texto) . '</span>')
            ->implode('');

        return new HtmlString(
            '<div style="display:flex;align-items:center;gap:0.75rem;">'
                . $imagenHtml
                . '<div><div style="font-weight:600;">' . e($producto->nombre) . '</div><div style="margin-top:0.25rem;">' . $badges . '</div></div>'
                . '</div>
                <div>
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Marca</th>
                                <th>Categoría</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div style="font-weight:600;">' . e($producto->nombre) . '</div>
                                    
                                </td>
                                <td>' . e($producto->marca?->nombre) . '</td>
                                <td>' . e($producto->categoria?->nombre) . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>'
        );
    }

    /**
     * @return array<int, string>
     */
    public static function opcionesVariantes(mixed $productoId): array
    {
        if (blank($productoId)) {
            return [];
        }

        return ProductoVariante::query()
            ->where('producto_id', $productoId)
            ->where('estado', true)
            ->get()
            ->mapWithKeys(fn(ProductoVariante $variante) => [
                $variante->id => static::labelVariante($variante),
            ])
            ->all();
    }

    public static function labelVariante(ProductoVariante $variante): string
    {
        $atributos = collect($variante->atributos ?? [])
            ->map(fn($valor, $clave) => Str::title((string) $clave) . ': ' . $valor)
            ->implode(', ');

        return $atributos !== ''
            ? "{$atributos} ({$variante->codigo_interno})"
            : $variante->codigo_interno;
    }
}
