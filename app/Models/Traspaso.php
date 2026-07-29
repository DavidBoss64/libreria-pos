<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TraspasoEstado;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'almacen_origen_id',
    'almacen_destino_id',
    'estado',
    'usuario_solicitante_id',
    'usuario_procesador_id',
])]
class Traspaso extends Model
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
            'estado' => TraspasoEstado::class,
        ];
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacenOrigen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuarioSolicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_solicitante_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuarioProcesador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_procesador_id');
    }

    /**
     * @return HasMany<TraspasoDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(TraspasoDetalle::class);
    }
}
