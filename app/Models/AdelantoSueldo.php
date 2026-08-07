<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdelantoSueldoEstado;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usuario_id', 'registrado_por_id', 'monto', 'fecha_adelanto', 'estado'])]
class AdelantoSueldo extends Model
{
    use HasFactory;

    /**
     * Sin esto, Eloquent adivina la tabla como "adelanto_sueldos" (pluralización
     * naive del último segmento) en vez de "adelantos_sueldo" (la tabla real,
     * DATABASE.md sección 7) — mismo tipo de bug ya encontrado en `ListaEscolar`
     * (Paso 5.1) y `Proveedor` (Paso 5.2).
     */
    protected $table = 'adelantos_sueldo';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_adelanto' => 'date',
            'estado' => AdelantoSueldoEstado::class,
        ];
    }

    /**
     * El empleado que recibió el adelanto.
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Quien entregó el dinero (siempre el admin autenticado que lo registra).
     *
     * @return BelongsTo<User, $this>
     */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
