<?php

use App\Console\Commands\CancelarPreventasVencidasCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fase 4, Paso 4.2 — libera cantidad_comprometida de pre-ventas abandonadas
// (LOGICA_NEGOCIO.md sección 3). Cada 5 min es un margen razonable frente a una
// expiración configurada en horas (config/ventas.php).
Schedule::command(CancelarPreventasVencidasCommand::class)->everyFiveMinutes();
