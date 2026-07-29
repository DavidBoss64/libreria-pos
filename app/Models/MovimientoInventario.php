<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimientoInventario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'almacen_id',
    'producto_variante_id',
    'tipo_movimiento',
    'cantidad',
    'saldo_despues',
    'motivo',
    'referencia_tipo',
    'referencia_id',
    'usuario_id',
])]
class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    /**
     * Registro de Kardex inmutable: solo tiene created_at, nunca se actualiza.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_movimiento' => TipoMovimientoInventario::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * @return BelongsTo<ProductoVariante, $this>
     */
    public function productoVariante(): BelongsTo
    {
        return $this->belongsTo(ProductoVariante::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
