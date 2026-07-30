# LÓGICA DE NEGOCIO Y REGLAS DEL SISTEMA - LIBRERÍA POS

Este documento centraliza todas las reglas de negocio, flujos operativos y observaciones acordadas con la gerencia para el sistema híbrido de la librería (Perú). Debe usarse como guía estricta para la validación de datos y la construcción de Actions/Services.

## 1. Estructura Organizacional y Multitenencia
El sistema opera bajo una estructura jerárquica para controlar el inventario y las ventas:
*   **Sucursales**: Puntos de venta físicos. Atienden al cliente final. **Regla:** toda Sucursal nueva genera automáticamente su propio Almacén de tipo `tienda` (mismo nombre) — evita que quede una sucursal sin punto de venta asignado por un error de configuración manual. El administrador puede agregar almacenes adicionales a esa sucursal (ej. un depósito propio) en cualquier momento.
*   **Almacenes**: Puntos de acopio de inventario bruto. Pueden abastecer a las sucursales.
*   **Regla de Aislamiento**: Un usuario (Vendedor/Cajero) pertenece a una Sucursal específica. Su vista de inventario, ventas y pre-ventas debe estar filtrada estrictamente para que solo vea los datos de su sucursal asignada. El Administrador tiene visibilidad global. **Implementación técnica:** ver `PLAN_DESARROLLO.md` Fase 1.5, Paso 1.5.5 (Global Scope reutilizable) — esta regla debe aplicarse desde el primer Resource de negocio que se cree en Fase 2, no dejarse para revisar al final.

## 2. Gestión de Usuarios y Roles (Control de Acceso)
El sistema opera con un control de acceso estricto (RBAC) dividido en cuatro roles operativos que no deben cruzarse. Esta separación se implementa en dos capas complementarias:
1.  **Arquitectura de paneles Filament** (tres paneles independientes: `admin`, `pos`, `almacen` — ver `CLAUDE.md`), que restringe a qué panel puede *entrar* cada rol.
2.  **Policies nativas de Laravel** basadas en `users.role` (ver `PLAN_DESARROLLO.md` Fase 1.5), que restringen qué *acciones* puede hacer cada rol dentro de su panel. No se usa `spatie/laravel-permission` — los 4 roles son fijos y no se prevén permisos configurables dinámicamente.


*   **Administrador / Gerencia** (panel `admin`): Acceso total al sistema. Gestión de catálogo maestro, reportes globales financieros, creación de usuarios, auditoría de ajustes y visión panorámica de todos los almacenes y sucursales.
*   **Almacenero** (panel `almacen`): Personal de logística. Su vista está restringida estrictamente al almacén o almacenes que tenga asignados (vía la tabla `almacen_usuario`, no vía `sucursal_id` — un Almacenero no "pertenece" a una sucursal, gestiona uno o más almacenes que pueden abastecer a varias sucursales). Sus funciones exclusivas son: recepcionar mercadería de proveedores, gestionar el stock bruto, realizar inventarios físicos en almacén y despachar los *traspasos* solicitados por las sucursales. No tiene acceso al módulo de ventas ni a las cajas.
*   **Vendedor** (panel `pos`): Personal en piso de sucursal. Su función es asesorar al cliente, revisar disponibilidad de stock en su tienda (y consultar si hay en almacén) y generar **Pre-ventas (Carritos)**. No manejan dinero, ni finalizan ventas, ni dan ingreso a mercadería nueva.
*   **Cajero** (panel `pos`): Personal en caja de sucursal. Su función es cobrar las pre-ventas generadas por los vendedores, recibir el pago, registrar la referencia bancaria/QR, emitir el comprobante interno y gestionar la apertura/cierre de su turno de caja. Vendedor y Cajero comparten el panel `pos`, pero se separan por `Policy` según la acción (crear pre-venta vs. cobrarla).

## 3. El Flujo de "Pre-venta" (Core del POS Físico)
Para evitar cuellos de botella en la tienda física, la venta se divide en dos etapas asíncronas:

### Fase A: Armado (Vendedor)
1. El cliente interactúa con el Vendedor.
2. El Vendedor abre una nueva "Pre-venta" en el sistema.
3. Escanea o busca los productos, seleccionando el nivel de precio adecuado (ver sección 4).
4. El sistema muestra el stock disponible y, de forma indicativa, cuánto está "comprometido" en otras pre-ventas activas (columna `cantidad_comprometida` en `inventarios`), pero **no bloquea ni reserva stock físicamente** en esta etapa.
5. El Vendedor guarda la Pre-venta. El sistema genera un "Ticket de Espera" o un número de orden, y asigna una fecha de expiración (`ventas.expira_en`) a la pre-venta.

### Fase B: Cobro (Cajero)
1. El cliente pasa a la caja con su número de orden.
2. El Cajero abre el módulo de "Cobros Pendientes" y selecciona la Pre-venta.
3. El Cajero selecciona el Método de Pago (Efectivo, Tarjeta, QR, Transferencia).
4. Si es pago digital, es **obligatorio** registrar el campo `referencia_pago` para la conciliación.
5. Se procesa la Venta Final dentro de una transacción con bloqueo de fila (`lockForUpdate()`) sobre cada línea de `inventarios` involucrada. Solo en este momento se descuenta oficialmente el stock físico y se registra el ingreso contable. Si algún ítem ya no tiene stock suficiente (por venta concurrente), ese ítem específico se rechaza y el Cajero decide cómo resolverlo con el cliente (no se cancela toda la venta automáticamente).

### Manejo de pre-ventas abandonadas
Un job programado revisa periódicamente las ventas en estado `pendiente` cuyo `expira_en` ya pasó, y las marca como `cancelado`, liberando su aporte a `cantidad_comprometida` en `inventarios`. Esto evita que canastas olvidadas distorsionen indefinidamente el indicador de stock comprometido.

## 4. Estructura de Precios (Multinivel)
Los productos no tienen un precio único. El sistema debe soportar un motor de precios dinámico al momento de agregar ítems al carrito:
*   **Precio Unitario**: Venta al detal.
*   **Precio por Docena**: Se activa automáticamente si la cantidad del ítem es ≥ 12 unidades. También puede seleccionarse manualmente.
*   **Precio Mayorista**: **Decisión (antes ambigua, ya resuelta):** se activa por **ambos** criterios — (a) automáticamente si la cantidad del ítem alcanza el umbral configurado en `configuracion_negocio.umbral_mayor` (editable por el `admin` desde el panel, ver `PLAN_DESARROLLO.md` Paso 4.4 — valor por defecto **24 unidades**, migrado desde `config/precios.php` original de Fase 2), o (b) manualmente, cuando el Vendedor/Cajero lo selecciona para un cliente recurrente conocido, sin importar la cantidad de esa compra puntual. Ambos caminos son válidos simultáneamente; el manual sirve como override del automático.
*   *Nota técnica*: El Action que calcula el total del carrito debe re-evaluar los subtotales si la cantidad de un ítem cambia de nivel, y debe registrar en `venta_detalles.tipo_precio_aplicado` qué nivel se usó (incluyendo si el mayorista vino de umbral automático o de selección manual, para trazabilidad — puede reflejarse en el `motivo`/metadata del registro si se requiere ese detalle).

### Visualización y edición de margen de ganancia (Fase 2)
El propietario necesita ver, al momento de definir/editar precios en el catálogo, cuánta ganancia le deja cada nivel de precio frente al `costo_real`. Esto se resuelve **sin tablas ni columnas nuevas**: el formulario de Filament (Paso 2.3) debe mostrar, junto a cada campo de precio (`precio_venta_unidad`, `precio_venta_docena`, `precio_venta_mayor`), un indicador reactivo de margen (`(precio - costo_real) / costo_real * 100`), calculado en vivo mientras se edita. Idealmente bidireccional: se puede escribir el precio y ver el margen resultante, o escribir el margen deseado y que sugiera el precio. Es un patrón estándar en catálogos de retail (Odoo, Square, Loyverse), de bajo costo de implementación porque es lógica de formulario, no de base de datos.

> **Nota para Fase 4/6 (a futuro, no urgente ahora):** si más adelante se requiere reportar la ganancia real de ventas históricas (no solo del catálogo actual), habrá que evaluar guardar una "foto" del costo en el momento de cada venta (`venta_detalles.costo_unitario_en_venta`), porque `costo_real` puede cambiar con el tiempo y distorsionaría retroactivamente los reportes de ganancia si no se congela. Se decide al llegar a esa fase.

## 5. Inventario y Logística (Omnicanal)
El catálogo de productos es uno solo (maestro), pero el stock está estrictamente dividido por ubicación geográfica (Almacenes y Sucursales).
*   **Ingreso de Mercadería**: Todo ingreso fuerte (compras a proveedores) entra por defecto a un Almacén y es gestionado por el Almacenero.
*   **Traspasos (Movimientos Internos)**: El flujo de mercadería de Almacén -> Sucursal requiere una solicitud y un cambio de estado (`solicitado`, `preparando`, `en_transito`, `completado`, `cancelado`) para evitar pérdidas en el camino. El Almacenero despacha, el administrador de la sucursal o cajero designado recibe. **Modelo "hub and spoke":** un mismo almacén central (`tipo: 'deposito'`) puede recibir solicitudes de traspaso de varias sucursales distintas — `traspasos` ya relaciona `almacen_origen_id` con `almacen_destino_id` (almacén a almacén), no sucursal a sucursal, así que esto no requiere ningún cambio de esquema. El Almacenero ve las solicitudes filtradas por **qué almacenes gestiona** (tabla `almacen_usuario`), sin importar de qué sucursal provenga cada solicitud.
    > **DECISIÓN TOMADA (Paso 3.3):** para el MVP, el propio Almacenero conduce el traspaso por las 4 transiciones (`solicitado` → `preparando` → `en_transito` → `completado`) desde el panel `almacen`, incluyendo el paso final que efectivamente mueve el stock (`CompletarTraspasoAction`, Paso 3.2). No existe todavía una confirmación de recepción separada a cargo de alguien en la sucursal destino — se simplifica así porque el negocio no tiene, por ahora, personal dedicado a "recibir" en tienda distinto del propio Almacenero que despacha. Si en el futuro se requiere una confirmación de recepción independiente (ej. el Cajero de la sucursal destino confirma que el paquete llegó físicamente), se reabre esta decisión como un paso adicional de estado.
    > **DECISIÓN TOMADA (Paso 3.6 — cumplimiento parcial):** durante el estado `preparando`, el Almacenero registra por cada línea del traspaso cuánto reunió realmente (`traspaso_detalles.cantidad_preparada`), que puede ser menor a lo solicitado si no hay stock suficiente. No se permite avanzar a `en_transito` hasta que todas las líneas tengan este valor registrado (aunque sea `0`). `CompletarTraspasoAction` mueve stock según lo `cantidad_preparada`, no lo solicitado — es la cantidad real que físicamente se traslada.
*   **Origen de Ventas (`origen`)**: Toda venta debe marcar de dónde provino: `pos` (piso de venta físico) o `ecommerce` (tienda web). Este es el único par de valores válido para el campo — debe coincidir exactamente con el enum definido en `DATABASE.md`.
*   **Ajustes Manuales**: Restringidos al Administrador (o al Almacenero con previa autorización en el sistema) para casos de merma, daño o pérdida, guardando siempre un registro de auditoría del usuario responsable en `movimientos_inventario`.

## 6. Comprobantes y Facturación
El sistema **no emite factura electrónica ni comprobante fiscal ante SUNAT**. `ventas.numero_ticket` es un comprobante interno de control de caja únicamente. Esta es una decisión de negocio explícita, no un vacío pendiente; si en el futuro se requiere facturación electrónica, será una fase nueva con su propio diseño (series, códigos QR SUNAT, integración con OSE/PSE, etc.).

## 7. Control de Caja (descartado para el MVP)
Se evaluó un módulo de apertura/cierre de turno de caja con arqueo de efectivo, pero se descarta para el MVP: el negocio es de un propietario único que no requiere esta capa de auditoría. La estadística de efectivo cobrado se obtiene igualmente a través de `ventas.metodo_pago`, sin necesidad de una tabla de turnos. Queda como mejora opcional si el negocio crece y contrata más personal de caja.

## 8. Reportería y Estadísticas (Panel Administrador)
El panel `admin` debe mostrarle al propietario, sin que él tenga que interpretar nada técnico, una vista general del negocio. Esto se construye enteramente a partir de `ventas` y `venta_detalles` (no requiere `turnos_caja` ni tablas adicionales):

*   **Resumen general:** total vendido y número de ventas en el período seleccionado (hoy/semana/mes), con comparación porcentual contra el período anterior equivalente.
*   **Producto más vendido:** ranking por cantidad de unidades (`venta_detalles` agrupado por `producto_variante_id`) y ranking por monto generado — no son necesariamente el mismo producto, y ambos son útiles.
*   **Vendedor con más ventas:** ranking por número de pre-ventas completadas y por monto total generado (`ventas.vendedor_id`), más un conteo de clientes atendidos (ventas completadas, distinguiendo `cliente_id` o `cliente_temporal`).
*   **Ventas por sucursal:** comparativo entre sucursales.
*   **Ventas por método de pago:** distribución entre efectivo, tarjeta, QR y transferencia.
*   **Ventas por origen:** `pos` vs. `ecommerce` (relevante desde que exista la Fase 7 — API y E-Commerce, reordenada al final de `PLAN_DESARROLLO.md`; hasta entonces todas las ventas son `pos`).
*   **Alertas de stock bajo:** productos cuya `inventarios.cantidad` está en o por debajo de `stock_minimo` (`cantidad <= stock_minimo`) — `stock_minimo` es el punto de reorden, se alerta al llegarlo o cruzarlo, no solo estrictamente por debajo.
*   **Categorías/marcas más vendidas.**

Todas estas consultas de agregación deben centralizarse en un `ReporteService` (siguiendo la regla de `CLAUDE.md` de que las consultas complejas van en Services), consumido por Widgets nativos de Filament (Stats Overview + Chart) en el panel `admin`. El panel `pos` y `almacen` no muestran estas estadísticas globales, salvo indicadores acotados a su propia sucursal/almacén si se decide más adelante.

## 9. Fidelización por Puntos (Fase 4 — diseño cerrado, no implementar aún)
El cliente acumula puntos de compra y puede decidir, al momento de pagar, si los canjea por descuento o los acumula para después.

*   **Acumulación:** por cada `configuracion_negocio.soles_por_punto` (default: **30 soles**, editable por el `admin`) del total de una venta, el cliente gana 1 punto. Solo aplica si la venta tiene `cliente_id` (cliente registrado) — un `cliente_temporal` (nombre rápido sin cuenta) no puede acumular, porque no existe una fila persistente en `clientes` a la que sumarle el saldo.
*   **Canje:** cada punto vale `configuracion_negocio.valor_por_punto` (propuesta de arranque: **S/ 0.30** por punto, ~1% de retorno, editable por el `admin`) de descuento directo en soles sobre el total de la compra — no un porcentaje. El Cajero pregunta al cliente si desea canjear puntos antes de cerrar la venta; si acepta, se descuenta el monto correspondiente del `total` y se registra `puntos_utilizados`/`descuento_por_puntos` en `ventas`.
*   **Principio de integridad (mismo que el Kardex de inventario):** `clientes.puntos_acumulados` NUNCA se modifica directamente — todo cambio (ganar, canjear, ajuste manual) pasa por un registro en `movimientos_puntos`, con `lockForUpdate()` sobre la fila del cliente dentro de la misma transacción de cierre de venta, para evitar condiciones de carrera si el mismo cliente tuviera dos ventas simultáneas.
*   **Ambos valores (`soles_por_punto`, `valor_por_punto`) viven en la tabla `configuracion_negocio`** (ver `DATABASE.md` sección 9 y `PLAN_DESARROLLO.md` Paso 4.4), editables por el propietario desde una pantalla del panel `admin` — nunca hardcodeados ni en un archivo de config que solo un desarrollador pueda tocar.
