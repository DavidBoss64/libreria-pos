<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'umbral_mayor',
    'soles_por_punto',
    'valor_por_punto',
])]
class ConfiguracionNegocio extends Model
{
    protected $table = 'configuracion_negocio';

    private const CACHE_KEY = 'configuracion_negocio.actual';

    protected function casts(): array
    {
        return [
            'umbral_mayor' => 'integer',
            'soles_por_punto' => 'integer',
            'valor_por_punto' => 'decimal:2',
        ];
    }

    /**
     * Fila única (id = 1), cacheada indefinidamente e invalidada al guardar cambios
     * (ver `booted()`). Se cachea el array de atributos, no la instancia del modelo:
     * `config('cache.serializable_classes')` está en `false` en este proyecto (default
     * de seguridad de Laravel 13), lo que hace que `unserialize()` descarte cualquier
     * objeto cacheado como `__PHP_Incomplete_Class` — solo arrays/escalares sobreviven.
     */
    public static function actual(): self
    {
        $atributos = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => self::query()->findOrFail(1)->getAttributes(),
        );

        return (new self)->newFromBuilder($atributos);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }
}
