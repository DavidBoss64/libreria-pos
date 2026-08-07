<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['compra_id', 'producto_variante_id', 'cantidad', 'precio_compra_unitario', 'subtotal'])]
class CompraDetalle extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_compra_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Compra, $this>
     */
    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    /**
     * `withTrashed()`: mismo motivo que el resto de los `*_detalles` históricos
     * (Paso 3.7/3.11) — una compra ya completada es un documento histórico, no debe
     * romperse si la variante fue enviada a la papelera después.
     *
     * @return BelongsTo<ProductoVariante, $this>
     */
    public function productoVariante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class)->withTrashed();
    }
}
