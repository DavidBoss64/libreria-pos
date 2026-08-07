<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'producto_id',
    'codigo_barras',
    'codigo_interno',
    'atributos',
    'unidades_por_caja',
    'costo_real',
    'precio_venta_unidad',
    'precio_venta_docena',
    'precio_venta_mayor',
    'estado',
])]
class ProductoVariante extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'atributos' => 'array',
            'unidades_por_caja' => 'integer',
            'costo_real' => 'decimal:2',
            'precio_venta_unidad' => 'decimal:2',
            'precio_venta_docena' => 'decimal:2',
            'precio_venta_mayor' => 'decimal:2',
            'estado' => 'boolean',
        ];
    }

    /**
     * `withTrashed()`: mismo motivo que `TraspasoDetalle::productoVariante()` — una
     * variante puede quedar referenciada en un documento histórico (traspaso) después de
     * que su producto padre haya sido enviado a la papelera de forma independiente.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    /**
     * @return HasMany<Inventario, $this>
     */
    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }
}
