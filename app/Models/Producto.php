<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'slug', 'marca_id', 'categoria_id', 'imagen_principal', 'estado'])]
class Producto extends Model
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
            'estado' => 'boolean',
        ];
    }

    /**
     * `withTrashed()`: mismo motivo que `ProductoVariante::producto()` — un producto
     * referenciado en un documento histórico puede seguir mostrándose aunque su marca
     * haya sido enviada a la papelera después.
     *
     * @return BelongsTo<Marca, $this>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class)->withTrashed();
    }

    /**
     * `withTrashed()`: mismo motivo que `marca()`, aplicado a categoría.
     *
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class)->withTrashed();
    }

    /**
     * @return HasMany<ProductoVariante, $this>
     */
    public function variantes(): HasMany
    {
        return $this->hasMany(ProductoVariante::class);
    }
}
