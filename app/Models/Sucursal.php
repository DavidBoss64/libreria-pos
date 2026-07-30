<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'direccion', 'estado'])]
class Sucursal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sucursales';

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
     * @return HasMany<Almacen, $this>
     */
    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Usado para bloquear el envío a la papelera de una sucursal cuyos
     * almacenes todavía tienen mercadería real (Paso 3.7).
     */
    public function tieneStockFisico(): bool
    {
        return $this->almacenes()
            ->whereHas('inventarios', fn ($query) => $query->where('cantidad', '>', 0))
            ->exists();
    }
}
