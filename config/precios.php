<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Umbral de Precio Mayorista
    |--------------------------------------------------------------------------
    |
    | Cantidad mínima de unidades a partir de la cual se aplica automáticamente
    | el precio mayorista. Valor por defecto pendiente de confirmación final
    | con el propietario (ver LOGICA_NEGOCIO.md sección 4). Debe ser mayor al
    | umbral de Docena (12) — PrecioService valida esto en tiempo de ejecución.
    |
    */
    'umbral_mayor' => (int) env('PRECIOS_UMBRAL_MAYOR', 24),
];
