<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['razon_social', 'nit_documento', 'contacto', 'telefono', 'estado'])]
class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Sin esto, Eloquent adivina la tabla como "proveedors" (pluralización naive en
     * inglés) en vez de "proveedores" (la tabla real, DATABASE.md sección 6) — mismo
     * tipo de bug ya encontrado y corregido en `ListaEscolar` (Paso 5.1).
     */
    protected $table = 'proveedores';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Compra, $this>
     */
    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}
