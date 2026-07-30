<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlmacenTipo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['sucursal_id', 'nombre', 'tipo', 'estado'])]
class Almacen extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'almacenes';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => AlmacenTipo::class,
            'estado' => 'boolean',
        ];
    }


    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * @return HasMany<Inventario, $this>
     */
    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }

    /**
     * Usuarios (Almaceneros) asignados a este almacén vía `almacen_usuario`.
     *
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'almacen_usuario', 'almacen_id', 'usuario_id')
            ->withTimestamps();
    }

    /**
     * Usado para bloquear el envío a la papelera de un almacén que todavía
     * tiene mercadería real (Paso 3.7) — evita "perder de vista" stock físico.
     */
    public function tieneStockFisico(): bool
    {
        return $this->inventarios()->where('cantidad', '>', 0)->exists();
    }
}
