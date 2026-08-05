<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombres', 'apellidos', 'documento', 'email', 'password', 'telefono', 'puntos_acumulados'])]
#[Hidden(['password'])]
class Cliente extends Model
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
            'password' => 'hashed',
            'puntos_acumulados' => 'integer',
        ];
    }

    /**
     * @return HasMany<Venta, $this>
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * Kardex de fidelización — mismo principio que `movimientos_inventario`: nunca se
     * modifica `puntos_acumulados` directamente (ver `RegistrarMovimientoPuntosAction`).
     *
     * @return HasMany<MovimientoPuntos, $this>
     */
    public function movimientosPuntos(): HasMany
    {
        return $this->hasMany(MovimientoPuntos::class);
    }
}
