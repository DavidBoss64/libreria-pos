<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['traspaso_id', 'producto_variante_id', 'cantidad', 'cantidad_preparada'])]
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
     * `withTrashed()`: un traspaso ya completado es un registro histórico — debe poder
     * seguir mostrando qué variante se movió aunque esa variante haya sido enviada a la
     * papelera después (Paso 3.7). Sin esto, el Infolist truena con "Attempt to read
     * property on null" al intentar mostrar un traspaso antiguo.
     *
     * @return BelongsTo<ProductoVariante, $this>
     */
    public function productoVariante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class)->withTrashed();
    }
}
