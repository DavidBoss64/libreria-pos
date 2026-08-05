<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Puntos\RegistrarMovimientoPuntosAction;
use App\Enums\TipoMovimientoPuntos;
use App\Exceptions\PuntosInsuficientesException;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RegistrarMovimientoPuntosActionTest extends TestCase
{
    use RefreshDatabase;

    private function crearCliente(int $puntosIniciales = 0): Cliente
    {
        return Cliente::create([
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'puntos_acumulados' => $puntosIniciales,
        ]);
    }

    public function test_ganado_suma_puntos_y_registra_kardex(): void
    {
        $cliente = $this->crearCliente();

        $movimiento = (new RegistrarMovimientoPuntosAction)->handle($cliente->id, TipoMovimientoPuntos::Ganado, 5);

        $this->assertSame(5, $movimiento->puntos);
        $this->assertSame(5, $movimiento->saldo_despues);
        $this->assertSame(5, $cliente->fresh()->puntos_acumulados);
    }

    public function test_canjeado_resta_puntos_y_registra_kardex_con_cantidad_negativa(): void
    {
        $cliente = $this->crearCliente(10);

        $movimiento = (new RegistrarMovimientoPuntosAction)->handle($cliente->id, TipoMovimientoPuntos::Canjeado, 4);

        $this->assertSame(-4, $movimiento->puntos);
        $this->assertSame(6, $movimiento->saldo_despues);
        $this->assertSame(6, $cliente->fresh()->puntos_acumulados);
    }

    /**
     * Invariante de negocio (suite normal, SQLite) exigida por CLAUDE.md regla 10: un
     * canje que excede el saldo disponible falla sin dejar el saldo negativo. La prueba
     * de bloqueo de fila real bajo concurrencia se reutiliza vía el mismo primitivo
     * (`lockForUpdate()`) ya cubierto por `InventarioConcurrenciaTest` (Fase 3) — no se
     * duplica un arnés de dos procesos aparte solo para puntos (mismo criterio que
     * `CerrarVentaAction`, ver PLAN_DESARROLLO.md Paso 4.2).
     */
    public function test_canje_que_excede_el_saldo_disponible_se_rechaza_sin_modificar_el_saldo(): void
    {
        $cliente = $this->crearCliente(3);

        $this->expectException(PuntosInsuficientesException::class);

        try {
            (new RegistrarMovimientoPuntosAction)->handle($cliente->id, TipoMovimientoPuntos::Canjeado, 10);
        } finally {
            $this->assertSame(3, $cliente->fresh()->puntos_acumulados, 'El saldo no debe cambiar cuando el canje se rechaza.');
            $this->assertSame(0, $cliente->movimientosPuntos()->count(), 'Un canje rechazado no debe dejar rastro en el Kardex.');
        }
    }

    public function test_ajuste_admite_delta_negativo(): void
    {
        $cliente = $this->crearCliente(10);

        $movimiento = (new RegistrarMovimientoPuntosAction)->handle($cliente->id, TipoMovimientoPuntos::Ajuste, -3);

        $this->assertSame(-3, $movimiento->puntos);
        $this->assertSame(7, $movimiento->saldo_despues);
    }

    public function test_puntos_cero_en_ganado_o_canjeado_lanza_excepcion(): void
    {
        $cliente = $this->crearCliente();

        $this->expectException(InvalidArgumentException::class);

        (new RegistrarMovimientoPuntosAction)->handle($cliente->id, TipoMovimientoPuntos::Ganado, 0);
    }

    public function test_ajuste_con_puntos_cero_lanza_excepcion(): void
    {
        $cliente = $this->crearCliente();

        $this->expectException(InvalidArgumentException::class);

        (new RegistrarMovimientoPuntosAction)->handle($cliente->id, TipoMovimientoPuntos::Ajuste, 0);
    }
}
