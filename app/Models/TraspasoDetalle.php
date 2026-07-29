<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['traspaso_id', 'producto_variante_id', 'cantidad'])]
class TraspasoDetalle extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<Traspaso, $this>
     */
    public function traspaso(): BelongsTo
    {
        return $this->belongsTo(Traspaso::class);
    }

    /**
     * @return BelongsTo<ProductoVariante, $this>
     */
    public function productoVariante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class);
    }
}
