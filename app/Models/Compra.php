<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompraEstado;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['proveedor_id', 'almacen_id', 'usuario_id', 'numero_factura', 'total', 'estado'])]
class Compra extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'estado' => CompraEstado::class,
        ];
    }

    /**
     * `withTrashed()`: mismo motivo que el resto de las relaciones históricas
     * (Paso 3.11) — una compra ya completada es un documento histórico, no debe
     * romperse si el proveedor fue enviado a la papelera después.
     *
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * El Admin o Almacenero que registró la compra.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return HasMany<CompraDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class);
    }
}
