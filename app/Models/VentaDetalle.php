<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoPrecioAplicado;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venta_id', 'producto_variante_id', 'cantidad', 'tipo_precio_aplicado', 'precio_unitario', 'subtotal'])]
class VentaDetalle extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_precio_aplicado' => TipoPrecioAplicado::class,
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * `withTrashed()`: una venta es un documento histórico — debe poder seguir mostrando
     * qué variante se vendió aunque esa variante haya sido enviada a la papelera después
     * (mismo principio aplicado a `TraspasoDetalle::productoVariante()`, ver Paso 3.11).
     *
     * @return BelongsTo<ProductoVariante, $this>
     */
    public function productoVariante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class)->withTrashed();
    }
}
