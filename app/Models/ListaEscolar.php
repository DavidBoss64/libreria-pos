<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre_plantilla', 'colegio', 'precio_total_estimado', 'es_plantilla'])]
class ListaEscolar extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Sin esto, Eloquent adivina la tabla como "lista_escolars" (pluralización en inglés
     * del último segmento) en vez de "listas_escolares" (la tabla real, DATABASE.md sección 5).
     */
    protected $table = 'listas_escolares';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_total_estimado' => 'decimal:2',
            'es_plantilla' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ListaEscolarDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(ListaEscolarDetalle::class);
    }

    /**
     * Recalcula y persiste `precio_total_estimado` sumando precio_venta_unidad × cantidad
     * de cada línea. Invocado por `ListaEscolarDetalleObserver` cada vez que cambia un
     * detalle — nunca se debe confiar en un valor escrito a mano en el formulario.
     */
    public function recalcularPrecioTotalEstimado(): void
    {
        $total = $this->detalles()
            ->with('productoVariante')
            ->get()
            ->reduce(
                fn (string $acumulado, ListaEscolarDetalle $detalle) => bcadd(
                    $acumulado,
                    bcmul((string) $detalle->productoVariante->precio_venta_unidad, (string) $detalle->cantidad, 2),
                    2
                ),
                '0.00'
            );

        $this->update(['precio_total_estimado' => $total]);
    }
}
