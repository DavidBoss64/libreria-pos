<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VentaEstado;
use App\Enums\VentaMetodoPago;
use App\Enums\VentaOrigen;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'numero_ticket',
    'sucursal_id',
    'usuario_id',
    'vendedor_id',
    'cliente_id',
    'cliente_temporal',
    'total',
    'metodo_pago',
    'referencia_pago',
    'origen',
    'estado',
    'expira_en',
])]
class Venta extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'metodo_pago' => VentaMetodoPago::class,
            'origen' => VentaOrigen::class,
            'estado' => VentaEstado::class,
            'expira_en' => 'datetime',
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
     * El Cajero que cobra (`null` en ventas web).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * El Vendedor que armó la canasta (`null` en ventas web).
     *
     * @return BelongsTo<User, $this>
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return HasMany<VentaDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }
}
