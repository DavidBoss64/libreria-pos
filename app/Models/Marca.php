<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'slug'])]
class Marca extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    /**
     * Usado para bloquear el envío a la papelera de una marca todavía en uso
     * (Paso 3.7) — evita que `Producto::marca` empiece a resolver null en
     * productos activos por el Global Scope de SoftDeletes.
     */
    public function tieneProductosActivos(): bool
    {
        return $this->productos()->exists();
    }
}
