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
    /**
     * Opciones del `Select` transitorio de Producto (paso 1 de la cascada) — productos
     * activos con marca/categoría en el label. Compartido entre `TraspasoForm` (pos) y
     * `VentaForm` (pos) para no mantener dos copias de la misma consulta.
     *
     * @return array<int, string>
     */
    public static function opcionesProductos(): array
    {
        return Producto::query()
            ->with(['marca', 'categoria'])
            ->where('estado', true)
            ->get()
            ->mapWithKeys(fn (Producto $producto) => [
                $producto->id => collect([
                    $producto->nombre,
                    $producto->marca?->nombre,
                    $producto->categoria?->nombre,
                ])->filter()->implode(' — '),
            ])
            ->all();
    }

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
            ? '<img src="'.e($producto->imagen_principal).'" style="height:48px;width:48px;object-fit:cover;border-radius:0.5rem;" onerror="this.style.display=\'none\'">'
            : '<div style="height:48px;width:48px;border-radius:0.5rem;background:rgb(228 228 231 / 0.5);"></div>';

        $celda = 'padding:0.5rem 0.75rem;border-bottom:1px solid rgb(228 228 231 / 0.5);text-align:left;';
        $encabezado = $celda.'font-size:0.75rem;text-transform:uppercase;color:rgb(113 113 122);font-weight:600;';

        return new HtmlString(
            '<div style="overflow-x:auto;">'
                .'<table style="width:100%;border-collapse:collapse;font-size:0.875rem;">'
                .'<thead><tr>'
                .'<th style="'.$encabezado.'">Imagen</th>'
                .'<th style="'.$encabezado.'">Producto</th>'
                .'<th style="'.$encabezado.'">Marca</th>'
                .'<th style="'.$encabezado.'">Categoría</th>'
                .'</tr></thead>'
                .'<tbody><tr>'
                .'<td style="'.$celda.'">'.$imagenHtml.'</td>'
                .'<td style="'.$celda.'font-weight:600;">'.e($producto->nombre).'</td>'
                .'<td style="'.$celda.'">'.e($producto->marca?->nombre ?? '—').'</td>'
                .'<td style="'.$celda.'">'.e($producto->categoria?->nombre ?? '—').'</td>'
                .'</tr></tbody>'
                .'</table>'
                .'</div>'
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
            ->mapWithKeys(fn (ProductoVariante $variante) => [
                $variante->id => static::labelVariante($variante),
            ])
            ->all();
    }

    public static function labelVariante(ProductoVariante $variante): string
    {
        $atributos = collect($variante->atributos ?? [])
            ->map(fn ($valor, $clave) => Str::title((string) $clave).': '.$valor)
            ->implode(', ');

        return $atributos !== ''
            ? "{$atributos} ({$variante->codigo_interno})"
            : $variante->codigo_interno;
    }

    /**
     * Opciones del buscador único de "Producto + Variante" (Paso de mejora de UX en
     * `VentaForm`): a diferencia de `opcionesProductos()`/`opcionesVariantes()` (cascada
     * en dos pasos), esta lista ya resuelve la variante exacta en un solo campo
     * buscable — el label combina nombre de producto + atributos + código interno +
     * código de barras (si existe), para que un lector de código de barras también
     * pueda "escribir y enter" sobre este mismo campo.
     *
     * @return array<int, string>
     */
    public static function opcionesVariantesConProducto(): array
    {
        return ProductoVariante::query()
            ->with('producto')
            ->where('estado', true)
            ->whereHas('producto', fn ($query) => $query->where('estado', true))
            ->get()
            ->mapWithKeys(fn (ProductoVariante $variante) => [
                $variante->id => static::labelVarianteConProducto($variante),
            ])
            ->all();
    }

    public static function labelVarianteConProducto(ProductoVariante $variante): string
    {
        $partes = [$variante->producto->nombre, static::labelVariante($variante)];

        if (filled($variante->codigo_barras)) {
            $partes[] = "Cód. barras: {$variante->codigo_barras}";
        }

        return implode(' — ', $partes);
    }

    public static function renderPreviewVariante(mixed $varianteId): HtmlString|string
    {
        if (blank($varianteId)) {
            return 'Producto agregado desde el buscador de arriba.';
        }

        $variante = ProductoVariante::query()->with(['producto.marca', 'producto.categoria'])->find($varianteId);

        if (! $variante) {
            return 'Producto agregado desde el buscador de arriba.';
        }

        $producto = $variante->producto;

        $imagenHtml = (filled($producto?->imagen_principal) && filter_var($producto->imagen_principal, FILTER_VALIDATE_URL))
            ? '<img src="'.e($producto->imagen_principal).'" style="height:48px;width:48px;object-fit:cover;border-radius:0.5rem;" onerror="this.style.display=\'none\'">'
            : '<div style="height:48px;width:48px;border-radius:0.5rem;background:rgb(228 228 231 / 0.5);"></div>';

        $celda = 'padding:0.5rem 0.75rem;border-bottom:1px solid rgb(228 228 231 / 0.5);text-align:left;';
        $encabezado = $celda.'font-size:0.75rem;text-transform:uppercase;color:rgb(113 113 122);font-weight:600;';

        return new HtmlString(
            '<div style="overflow-x:auto;">'
                .'<table style="width:100%;border-collapse:collapse;font-size:0.875rem;">'
                .'<thead><tr>'
                .'<th style="'.$encabezado.'">Imagen</th>'
                .'<th style="'.$encabezado.'">Producto</th>'
                .'<th style="'.$encabezado.'">Variante</th>'
                .'<th style="'.$encabezado.'">Marca</th>'
                .'<th style="'.$encabezado.'">Categoría</th>'
                .'</tr></thead>'
                .'<tbody><tr>'
                .'<td style="'.$celda.'">'.$imagenHtml.'</td>'
                .'<td style="'.$celda.'font-weight:600;">'.e($producto?->nombre ?? '—').'</td>'
                .'<td style="'.$celda.'">'.e(static::labelVariante($variante)).'</td>'
                .'<td style="'.$celda.'">'.e($producto?->marca?->nombre ?? '—').'</td>'
                .'<td style="'.$celda.'">'.e($producto?->categoria?->nombre ?? '—').'</td>'
                .'</tr></tbody>'
                .'</table>'
                .'</div>'
        );
    }
}
