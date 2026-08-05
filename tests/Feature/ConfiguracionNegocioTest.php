<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfiguracionNegocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConfiguracionNegocioTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_devuelve_la_fila_unica_con_los_valores_por_defecto(): void
    {
        $configuracion = ConfiguracionNegocio::actual();

        $this->assertSame(1, $configuracion->id);
        $this->assertSame(24, $configuracion->umbral_mayor);
        $this->assertSame(30, $configuracion->soles_por_punto);
        $this->assertSame('0.30', $configuracion->valor_por_punto);
    }

    public function test_actual_cachea_la_fila_y_no_refleja_cambios_hechos_por_fuera_del_modelo(): void
    {
        ConfiguracionNegocio::actual();

        ConfiguracionNegocio::query()->where('id', 1)->update(['umbral_mayor' => 50]);

        $this->assertSame(24, ConfiguracionNegocio::actual()->umbral_mayor);
    }

    public function test_guardar_un_cambio_invalida_la_cache(): void
    {
        $configuracion = ConfiguracionNegocio::actual();
        $configuracion->update(['umbral_mayor' => 50]);

        $this->assertSame(50, ConfiguracionNegocio::actual()->umbral_mayor);
    }

    /**
     * `config('cache.serializable_classes')` está en `false` en este proyecto — un default
     * de seguridad de Laravel 13 que hace que `unserialize()` descarte cualquier objeto
     * cacheado (lo convierte en `__PHP_Incomplete_Class`), aunque el store de pruebas
     * (`array`) no serialice de verdad y por eso no detecta el problema por sí solo. Este
     * test fija el contrato real: lo cacheado debe ser un array plano, nunca la instancia
     * del modelo, para sobrevivir intacto en `database`/`file`/`redis`.
     */
    public function test_lo_cacheado_es_un_array_plano_no_la_instancia_del_modelo(): void
    {
        ConfiguracionNegocio::actual();

        $this->assertIsArray(Cache::get('configuracion_negocio.actual'));
    }
}
