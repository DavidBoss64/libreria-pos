<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Expiración de Pre-ventas
    |--------------------------------------------------------------------------
    |
    | Minutos que una pre-venta permanece en estado 'pendiente' antes de que el
    | job programado (ventas:cancelar-preventas-vencidas) la marque 'anulado' y
    | libere su cantidad_comprometida. Valor confirmado con el propietario: 1
    | hora. Temporal aquí (como umbral_mayor lo fue en config/precios.php,
    | Fase 2) hasta que el Paso 4.4 cree `configuracion_negocio` y lo migre ahí.
    |
    */
    'expira_preventa_minutos' => (int) env('VENTAS_EXPIRA_PREVENTA_MINUTOS', 60),
];
