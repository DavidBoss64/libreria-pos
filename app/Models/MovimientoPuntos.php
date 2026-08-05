<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimientoPuntos;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id',
    'venta_id',
    'tipo',
    'puntos',
    'saldo_despues',
    'usuario_id',
])]
class MovimientoPuntos extends Model
{
    protected $table = 'movimientos_puntos';

    /**
     * Registro de Kardex inmutable: solo tiene created_at, nunca se actualiza.
     */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoPuntos::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<Venta, $this>
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Quien procesó el ajuste manual (`null` si fue automático desde el cierre de una venta).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
