<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lista_escolar_id', 'producto_variante_id', 'cantidad'])]
class ListaEscolarDetalle extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<ListaEscolar, $this>
     */
    public function listaEscolar(): BelongsTo
    {
        return $this->belongsTo(ListaEscolar::class);
    }

    /**
     * `withTrashed()`: mismo motivo que `TraspasoDetalle::productoVariante()` (Paso 3.7/3.11)
     * — una plantilla puede seguir referenciando una variante que después se envió a la
     * papelera; no debe romper la vista de administración de la plantilla.
     *
     * @return BelongsTo<ProductoVariante, $this>
     */
    public function productoVariante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class)->withTrashed();
    }
}
