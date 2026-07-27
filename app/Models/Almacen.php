<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlmacenTipo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
