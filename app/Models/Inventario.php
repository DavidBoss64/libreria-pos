<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['almacen_id', 'producto_variante_id', 'cantidad', 'cantidad_comprometida', 'stock_minimo'])]
class Inventario extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * @return BelongsTo<ProductoVariante, $this>
     */
    public function productoVariante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class);
    }
}
