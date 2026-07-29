# PLAN DE DESARROLLO Y LÓGICA DE NEGOCIO (SISTEMA LIBRERÍA HÍBRIDO)

Este documento define la hoja de ruta del proyecto, dividida en Fases (Sprints). Cada fase debe completarse y probarse antes de iniciar la siguiente.

**Cómo usar este archivo con Claude Code:** los checkboxes (`- [ ]` / `- [x]`) son la única fuente de verdad de qué está hecho. Al terminar una sesión de trabajo, pide explícitamente que se marquen aquí los pasos completados. Al iniciar una sesión nueva, pide que se revise este archivo antes de escribir código, para retomar exactamente donde quedó.

---

## FASE 1: Cimientos, Jerarquía y Autenticación
**Objetivo:** Levantar la base del sistema, configurar PostgreSQL y establecer quién usa el sistema y desde dónde.

- [x] **Paso 1.1: Setup Base:** Instalación de Laravel 13, configuración de la base de datos y despliegue del panel Filament v5.
- [x] **Paso 1.2: Migraciones y Modelos Base:** `sucursales`, `almacenes` y `users`.
- [x] **Paso 1.3: Lógica de Negocio (Jerarquía):**
    - Un usuario (`User`) siempre pertenece a una `Sucursal` (excepto el Super Admin).
    - Una `Sucursal` tiene al menos un `Almacen` (puede ser 'tienda' para ventas o 'deposito' para guardado).
- [x] **Paso 1.4: Interfaz — ARQUITECTURA MULTI-PANEL:**
    - El sistema usa **tres paneles Filament independientes**: `admin` (Administrador/Gerencia), `pos` (Vendedor + Cajero) y `almacen` (Almacenero). Ver reglas detalladas en `CLAUDE.md`.
    - [x] Panel base en `/admin` (instalación por defecto de Filament) con los Recursos de Sucursales, Almacenes y Usuarios. Este panel se conserva tal cual como el futuro panel `admin`.
    - [x] Crear panel `pos` (`php artisan make:filament-panel pos`).
    - [x] Crear panel `almacen` (`php artisan make:filament-panel almacen`).
    - [x] Implementar `canAccessPanel(Panel $panel)` en el modelo `User` para restringir login por rol.
- [x] **Paso 1.4.1: Autogestión de cuenta (perfil y recuperación de contraseña):**
    - [x] Página de perfil (`->profile()`) habilitada en los 3 paneles (`admin`, `pos`, `almacen`), con formulario propio (`App\Filament\Auth\EditProfile`) que usa `nombres`/`apellidos` en vez del campo `name` por defecto de Filament.
    - [x] Recuperación de contraseña (`->passwordReset()`) habilitada en los 3 paneles, usando el broker nativo de Laravel y el mailer configurado en `.env` (`MAIL_MAILER=log` en desarrollo).

> **PENDIENTE ANTES DE PRODUCCIÓN:** `MAIL_MAILER=log` solo escribe el correo de recuperación de contraseña en `storage/logs/laravel.log`, no lo envía realmente. Configurar un mailer real (SMTP, Mailgun, Resend, SES) antes de salir a producción.

---

## FASE 1.5: Seguridad, Roles, Permisos y Aislamiento por Sucursal
**Objetivo:** Blindar el sistema asegurando que cada usuario solo vea y haga lo que le corresponde según su rol operativo, **y** solo los datos de su propia sucursal/almacén — usando exclusivamente herramientas nativas de Laravel/Filament.

> **Decisión de arquitectura:** se usa `Policies` de Laravel basadas directamente en el campo `users.role` (ya existente como `enum`), **no** `spatie/laravel-permission` ni `Filament Shield`. Los 4 roles son fijos y cerrados según `LOGICA_NEGOCIO.md` sección 2 — no se prevén roles nuevos ni permisos configurables por el propietario. Agregar un paquete de permisos dinámicos (con sus tablas `roles`/`permissions`/pivots) sería una fuente de verdad duplicada frente al enum, y complejidad innecesaria para este negocio. Si en el futuro se requieren permisos configurables desde la UI, se reevalúa esta decisión.

- [x] **Paso 1.5.1: Helpers de rol en el modelo `User`:** métodos auxiliares (`isAdmin()`, `isVendedor()`, `isCajero()`, `isAlmacenero()`) para simplificar chequeos repetidos en Policies y en `canAccessPanel()`.
- [x] **Paso 1.5.2: Policies por recurso:** crear `SucursalPolicy`, `AlmacenPolicy` y `UserPolicy` — **regla estricta:** solo `admin` puede `viewAny/create/update/delete` sobre Sucursales, Almacenes y Usuarios. Los demás roles no deben ver estos menús ni acceder por URL directa.
- [x] **Paso 1.5.3: Registro en Filament:** vincular las Policies a los Resources correspondientes en el panel `admin` (Filament las respeta automáticamente si siguen la convención de nombres de Laravel). Incluye los métodos plurales (`deleteAny`/`forceDeleteAny`/`restoreAny`) requeridos por las bulk actions — ver regla 13 de `CLAUDE.md`.
- [x] **Paso 1.5.4: Verificación manual:** iniciar sesión con un usuario de cada rol y confirmar que cada uno ve y puede hacer únicamente lo descrito en `LOGICA_NEGOCIO.md` sección 2 (ej. un Vendedor no debe poder entrar a `/admin` ni ver el Resource de Usuarios).
- [x] **Paso 1.5.5: Aislamiento por sucursal (Trait reutilizable):** crear `app/Filament/Concerns/AislaPorSucursal.php`, un Trait para Resources de Filament que sobreescribe `getEloquentQuery()` (no un Global Scope de Modelo — evita romper Jobs/Commands/Services que corren sin usuario autenticado). Documentado y listo para usarse desde el primer Resource de negocio que se cree en Fase 2 en adelante. No se aplica a ningún Resource todavía porque `Sucursal`, `Almacen` y `User` no lo necesitan (son de gestión exclusiva del `admin`, ya cubierto por Policies).

> **Nota sobre verificación de aislamiento:** no se puede probar el Trait de forma real hasta que exista el primer "Resource de negocio" (uno que un Vendedor/Cajero/Almacenero vea con datos propios de su sucursal, ej. `Inventario`, `Venta`, `Traspaso` en Fase 3/4). Por eso esa verificación NO es un paso de la Fase 1.5 — es una **regla recurrente** (ver `CLAUDE.md` regla 9 actualizada): cada vez que se cree un Resource de negocio en Fases 3+, ese mismo paso debe incluir aplicar el Trait y verificar con dos usuarios de sucursales distintas antes de marcarse como completado. La Fase 1.5 se da por completada con los Pasos 1.5.1 a 1.5.5.

---

## FASE 2: Catálogo de Productos y Precios Dinámicos
**Objetivo:** Estructurar el inventario teórico (lo que se vende, no las cantidades aún) y la lógica de precios.

- [x] **Paso 2.1: Migraciones:** `marcas`, `categorias`, `productos` y `producto_variantes`. Modelos con relaciones y casts. Verificado con migración real en PostgreSQL y test de relaciones/casts.
- [x] **Paso 2.2: Lógica de Negocio (Precios Escalonados):**
    - Un producto general (ej. "Cuaderno 100 hojas") tiene variantes (ej. "Rojo", "Azul").
    - Cada variante maneja un `codigo_interno` (búsqueda rápida) y un `codigo_barras`.
    - **Regla de Precios:** Se definen tres precios (`unidad`, `docena`, `mayor`). El sistema debe ser capaz de consultar el Service correspondiente para saber qué precio aplicar según la cantidad que el cliente lleve.
    - **Precio Mayorista (decisión ya resuelta, ver `LOGICA_NEGOCIO.md` sección 4):** se activa por umbral automático de cantidad (configurable en `config/precios.php`, valor por defecto 24 unidades — confirmar con el propietario) **o** por selección manual del Vendedor/Cajero para clientes recurrentes. Nunca hardcodear el umbral en el Service.
    - **Implementado:** `App\Enums\TipoPrecioAplicado` (unidad/docena/mayor), `App\Services\PrecioService::calcularPrecio(int $productoVarianteId, int $cantidad, bool $forzarMayorista = false): PrecioCalculado` (math decimal exacto vía `bcmul`, valida `cantidad > 0` con `InvalidArgumentException` y `config('precios.umbral_mayor') > 12` con `RuntimeException`), `App\Services\PrecioCalculado` (DTO de solo lectura). Verificado con 6 casos de prueba (unidad, docena, mayor automático, mayor forzado, cantidad inválida, umbral mal configurado).
- [x] **Paso 2.3: Interfaz:** Formularios en el panel `admin` (catálogo maestro) para crear productos con sus variantes y precios. El panel `pos` solo consulta (read-only) para armar pre-ventas. **Incluir calculadora de margen:** junto a cada campo de precio, mostrar en vivo el margen de ganancia resultante frente a `costo_real` (campo reactivo de Filament, sin columnas nuevas en base de datos), idealmente editable en ambos sentidos (precio → margen, o margen → precio sugerido).
    - **Implementado:** `MarcaResource`/`CategoriaResource` (slug auto-generado + `unique(ignoreRecord: true)`) y `ProductoResource` en el panel `admin`, con `VariantesRelationManager` anidado (calculadora de margen bidireccional que maneja `costo_real` vacío/0 sin dividir por cero). `App\Filament\Pos\Resources\Productos\ProductoResource` de solo lectura (únicamente páginas `index`/`view`, sin `create`/`edit`) en el panel `pos`. 4 Policies nuevas (`MarcaPolicy`, `CategoriaPolicy`, `ProductoPolicy`, `ProductoVariantePolicy`, las 10 habilidades cada una — `viewAny`/`view` abiertas a cualquier usuario activo en las 2 últimas). `storage:link` creado. Verificado con tests (admin CRUD, edición de variantes, catálogo read-only del vendedor en `pos`, bloqueo 403 de `/admin` para roles no-admin, y los 6 casos del cálculo de margen incluyendo costo cero/vacío/no numérico).

> **REVISIÓN POST-COMPLETADO (Paso 2.3): imagen de producto por URL externa, no por archivo subido al servidor.** Decisión del propietario: en vez de `FileUpload` (que sube el archivo y lo guarda en el disco del proyecto), `imagen_principal` se llena pegando una URL de una imagen ya alojada en un servicio externo en la nube (ej. Cloudinary u otro que use el equipo). **Implementado:** `ProductoForm.php` (admin) usa `TextInput::make('imagen_principal')->url()` con **vista previa en vivo** (`->live(debounce: '500ms')` + `TextEntry::make('imagen_preview')->html()->state(...)` que renderiza `<img>` y valida la URL antes de intentar cargarla). No requiere ningún cambio de esquema (`imagen_principal` ya era `varchar(255)`, pensado para ruta o URL desde el inicio). Ya no aplica la regla 15 de `CLAUDE.md` (`storage:link`) a este campo específico, porque no hay archivo que Laravel gestione localmente.
>
> **REVISIÓN POST-COMPLETADO (Paso 2.3): catálogo `pos` reordenado visualmente.** `ProductoInfolist.php` (vista "Ver producto" del Vendedor/Cajero) agrupa imagen + nombre + marca + categoría en una `Section`/`Grid`, y la sección "Variantes" ahora incluye `atributos` (jsonb) como badges (`Color: rojo`, `Medida: 1 metro`) junto a los 3 precios de venta — `costo_real` sigue sin exponerse en este panel. `ProductosTable.php` (índice) agrupa marca/categoría como subtexto bajo el nombre del producto para lectura rápida tipo catálogo. Verificado con `tests/Feature/CatalogoUiSmokeTest.php` (admin ve el preview de URL, `pos` no filtra `costo_real` y renderiza los badges de atributos correctamente).

> **DECISIÓN TOMADA (previamente pendiente):** no se implementa `unidad_medida` ni columnas `decimal` en `cantidad` por ahora. Todos los productos (incluida cartulina/forro/contact) se modelan como piezas, rollos o pliegos **enteros**, usando el campo `atributos` (jsonb, ya existente en `producto_variantes`) para describir color, medida o presentación (ej. `{"color": "rojo", "medida": "1 metro"}`) — cada combinación es su propia variante con su propio precio y stock, sin necesidad de ningún cambio de esquema. **Condición para reabrir esta decisión:** si en el futuro aparece un producto real donde el Vendedor deba vender una cantidad fraccionada a pedido (ej. cortar 2.5 metros de un rollo), ahí sí se implementa `unidad_medida` + `decimal` en las columnas `cantidad` de `inventarios`/`movimientos_inventario`/`venta_detalles`/`compra_detalles`/`traspaso_detalles`/`lista_escolar_detalles`, como una fase dedicada — no antes.

---

## FASE 3: Motor de Inventario y Traspasos (El Kardex)
**Objetivo:** Controlar el stock real. Esta es la parte más crítica del backend; tolerancia a fallos cero.

- [x] **Paso 3.1: Migraciones:** `inventarios` (con `unique(['almacen_id','producto_variante_id'])` y columna `cantidad_comprometida`), `movimientos_inventario`, `traspasos`, `traspaso_detalles`, `almacen_usuario` (pivote de asignación Almacenero↔Almacén, con `unique(['usuario_id','almacen_id'])` — ver `DATABASE.md` sección 1 y `LOGICA_NEGOCIO.md` sección 2).
    - **Implementado:** las 5 migraciones (orden: `almacen_usuario` → `inventarios` → `movimientos_inventario` → `traspasos` → `traspaso_detalles`), aplicadas y verificadas contra PostgreSQL real. Enums nuevos `App\Enums\TipoMovimientoInventario` (ingreso/salida/ajuste) y `App\Enums\TraspasoEstado` (solicitado/preparando/en_transito/completado/cancelado), ambos con `HasColor`/`HasLabel` para Filament. Modelos nuevos `Inventario`, `MovimientoInventario` (Kardex inmutable: `$timestamps = false`, solo `created_at`), `Traspaso` (con `almacenOrigen()`/`almacenDestino()`/`usuarioSolicitante()`/`usuarioProcesador()`/`detalles()`) y `TraspasoDetalle`. Se agregó `User::almacenes()` (`belongsToMany` vía `almacen_usuario`, modelo hub-and-spoke) y sus relaciones inversas en `Almacen` (`inventarios()`, `usuarios()`) y `ProductoVariante` (`inventarios()`, `movimientosInventario()`). Verificado con `tests/Feature/InventarioKardexEstructuraTest.php` (restricción única de `inventarios`, restricción única de `almacen_usuario`, relación hub-and-spoke con 2 almacenes para un mismo Almacenero, cast del enum `tipo_movimiento` + ausencia de `updated_at` en el Kardex, y creación de un `Traspaso` con `TraspasoDetalle` y estado por defecto `solicitado`).
- [x] **Paso 3.2: Lógica de Negocio (Kardex Estricto):**
    - **Regla de Oro:** NUNCA se actualiza la tabla `inventarios` directamente.
    - Se debe crear una clase `InventoryAction` que reciba: el producto, el almacén, la cantidad y el motivo. Esta clase actualiza el stock, usa `lockForUpdate()` sobre la fila de `inventarios` dentro de `DB::transaction()`, y crea el registro inmutable en `movimientos_inventario`.
    - **Traspasos:** Flujo manual donde un vendedor solicita stock al depósito. Cambia de estados (`solicitado` -> `en_transito` -> `completado`). Al completarse, el `InventoryAction` saca stock de un almacén y lo mete en otro.
    - **Relación `User` ↔ `Almacen`:** agregar `belongsToMany(Almacen::class, 'almacen_usuario')` al modelo `User`, para soportar que un Almacenero gestione uno o varios almacenes (modelo "hub and spoke": un almacén central puede abastecer a varias sucursales). *(agregada en el Paso 3.1.)*
    - **Implementado:** `App\Actions\Inventario\RegistrarMovimientoInventarioAction::handle()` — único punto de entrada para tocar `inventarios`. Usa `Inventario::upsert(..., update: ['almacen_id'])` (no-op) para garantizar la fila sin condición de carrera, luego `lockForUpdate()` dentro de `DB::transaction()`, calcula el delta según `TipoMovimientoInventario` (ingreso: +cantidad; salida: -cantidad; ajuste: delta con signo libre para merma/corrección), rechaza con `App\Exceptions\StockInsuficienteException` si el saldo resultante sería negativo, y crea el `MovimientoInventario` inmutable (`cantidad` guarda el delta con signo, así `saldo_despues` siempre reconcilia con la suma del Kardex). `App\Actions\Traspasos\CompletarTraspasoAction::handle()` reutiliza el Action anterior dos veces (salida en origen + ingreso en destino) por cada `TraspasoDetalle`, dentro de una única transacción — si cualquier línea falla por stock insuficiente, se revierte todo el traspaso y su estado permanece `en_transito`. Solo transiciona un traspaso `en_transito` a `completado`; lanza `RuntimeException` en cualquier otro estado. Verificado con `tests/Feature/RegistrarMovimientoInventarioActionTest.php` (7 casos: ingreso, salida, salida sin stock no deja rastro en Kardex, invariante secuencial de sobreventa, ajuste con delta negativo, validaciones de cantidad inválida) y `tests/Feature/CompletarTraspasoActionTest.php` (3 casos: traspaso exitoso mueve stock entre almacenes, traspaso con stock insuficiente revierte todo sin dejar inventario fantasma en destino, y rechazo de completar un traspaso que no está `en_transito`). **Concurrencia real** (regla 17 de `CLAUDE.md`, nueva): `tests/Feature/Concurrency/InventarioConcurrenciaTest.php` (`#[Group('concurrency')]`, excluido del run por defecto vía `phpunit.xml`) lanza dos procesos de PHP independientes contra el Postgres real de `.env` y confirma que dos salidas concurrentes de 7 unidades sobre un stock de 10 nunca sobrevenden: exactamente una tiene éxito, la otra falla con stock insuficiente, y el Kardex queda con un solo registro consistente. Se corre por separado con `php artisan test --group=concurrency`.
- [ ] **Paso 3.3: Interfaz:** Tablas en el panel `almacen` para ver el stock actual y gestionar/aprobar traspasos entrantes; en el panel `pos`, solo consulta de stock + creación de solicitudes de traspaso por el Vendedor. **Recordatorio (CLAUDE.md regla 9):** estos son los primeros "Resources de negocio" — aplicar `use AislaPorSucursal;` (creado en Fase 1.5.5) al Resource de Traspasos/Inventario del panel `pos`, y crear + aplicar `AislaPorAlmacen` (nuevo, mismo patrón pero filtrando por `almacen_usuario`) al Resource de Traspasos del panel `almacen`. Verificar con dos Almaceneros de almacenes distintos, y con dos Vendedores de sucursales distintas, antes de marcar este paso como completado.


> **DECISIÓN TOMADA (previamente nota abierta):**
> 1. **Reserva de stock en Pre-venta:** se descartó la reserva dura (bloqueo físico al armar la canasta) por complejidad innecesaria para el volumen normal del negocio. En su lugar: (a) `inventarios.cantidad_comprometida` se mantiene como indicador visual no bloqueante para el Vendedor; (b) la validación real de disponibilidad ocurre en el cierre de venta (Fase 4), con `lockForUpdate()` para evitar condiciones de carrera entre cajeros simultáneos; (c) las pre-ventas `pendiente` expiran automáticamente (`ventas.expira_en` + job programado) para no inflar `cantidad_comprometida` con canastas abandonadas.
> 2. **Restricción única en `inventarios`:** resuelto — `unique(['almacen_id', 'producto_variante_id'])` agregado a la migración 3.1 en `DATABASE.md`.
> 3. **Modelo "hub and spoke" (Almacenero ↔ Almacenes):** un Almacenero se asigna a uno o varios almacenes vía la tabla pivote `almacen_usuario`, no vía `sucursal_id`. Un mismo almacén central puede recibir solicitudes de traspaso de varias sucursales — `traspasos` ya relaciona almacén con almacén, no sucursal con sucursal, así que no requiere cambios adicionales de esquema.

---

## FASE 4: Módulo de Ventas POS (Pre-venta)
**Objetivo:** El corazón de la tienda física. Flujo rápido para evitar filas largas.

- [ ] **Paso 4.1: Migraciones:** `clientes`, `ventas` (con `expira_en`), `venta_detalles` (con `tipo_precio_aplicado`).
- [ ] **Paso 4.2: Lógica de Negocio (Flujo Pre-venta):**
    - **Vendedor:** Atiende al cliente, arma la canasta en el sistema. Se crea una `venta` con estado `pendiente`, `origen = 'pos'` y se le asigna un `cliente_temporal` (ej. "Juan Polera Roja").
    - **Cajero:** Busca "Juan Polera Roja" en su pantalla. Confirma los productos.
    - **Pago:** Se selecciona método (Efectivo, QR, etc.). Si es QR, se guarda el ID del banco en `referencia_pago`.
    - **Cierre:** El sistema ejecuta el `InventoryAction` de la Fase 3, descuenta el stock y pasa la venta a `completado`. No se emite comprobante fiscal, solo el `numero_ticket` interno.
    - **Nota:** se descartó el control de apertura/cierre de caja (`turnos_caja`) para el MVP — ver `LOGICA_NEGOCIO.md` sección 7. Queda como mejora futura opcional.
- [ ] **Paso 4.3: Interfaz:** Pantalla POS optimizada en el panel `pos` para Vendedores (armar pedido) y Cajeros (cobrar). **Recordatorio (CLAUDE.md regla 9):** aplicar `use AislaPorSucursal;` al Resource de `Venta` (columna `sucursal_id` directa) y verificar con dos usuarios de sucursales distintas antes de marcar este paso como completado.
- [ ] **Paso 4.4: Configuración de Negocio editable por el admin (tabla `configuracion_negocio`, ver `DATABASE.md` sección 9):** migración de la tabla (fila única), modelo `ConfiguracionNegocio` con accessor cacheado (`ConfiguracionNegocio::actual()`, invalidado al guardar), y una Page dedicada en el panel `admin` (no un Resource) para editar `umbral_mayor`, `soles_por_punto`, `valor_por_punto`. **Incluye migrar** el `PrecioService` de Fase 2 para que lea `umbral_mayor` desde `ConfiguracionNegocio::actual()` en vez de `config('precios.umbral_mayor')` — `config/precios.php` queda obsoleto después de este paso.
- [ ] **Paso 4.5: Fidelización por puntos (ver `LOGICA_NEGOCIO.md` sección 9, diseño ya cerrado):** migraciones de `clientes.puntos_acumulados` y `movimientos_puntos`, columnas `puntos_ganados`/`puntos_utilizados`/`descuento_por_puntos` en `ventas`, y la lógica en el Action de cierre de venta: calcular puntos ganados, ofrecer canje al Cajero si el cliente está registrado, aplicar `lockForUpdate()` sobre la fila del cliente, y registrar el movimiento en `movimientos_puntos` (mismo principio que el Kardex de inventario — nunca modificar `puntos_acumulados` directamente). Lee `soles_por_punto`/`valor_por_punto` desde `ConfiguracionNegocio::actual()` (Paso 4.4), no desde un archivo de config.

---

## FASE 5: Optimizaciones (Plantillas y Compras)
**Objetivo:** Agilizar procesos recurrentes de la librería.

- [ ] **Paso 5.1: Plantillas Escolares:** Desarrollo de `listas_escolares`. Lógica para que un vendedor seleccione "2do Básico - San Calixto" y el sistema agregue 15 productos a la canasta de golpe.
- [ ] **Paso 5.2: Compras a Proveedores:** Ingreso de mercadería nueva (`compras`, `proveedores`). Al confirmar una compra, el sistema inyecta stock a través de `InventoryAction`.
- [ ] **Paso 5.3: Adelantos de Sueldo:** Un módulo simple (CRUD) para registrar dinero entregado a empleados antes de fin de mes.

---

## FASE 6: API y E-Commerce (El Frontend Externo)
**Objetivo:** Conectar la tienda web de React al núcleo de Laravel.

- [ ] **Paso 6.1: API Endpoints:** Crear rutas de API protegidas para el catálogo de productos.
- [ ] **Paso 6.2: Autenticación Web:** Permitir a los clientes iniciar sesión usando **Laravel Sanctum** (decidido — más simple que JWT y nativo de Laravel; adecuado para una SPA en React que consume la misma API, sin necesitar la complejidad de tokens firmados de JWT).
- [ ] **Paso 6.3: Lógica de Carrito Web:** Cuando la app de React envía una orden confirmada, Laravel reutiliza EXACTAMENTE la misma lógica de creación de venta y descuento de stock (Fase 4 y Fase 3), pero marcando el `origen` como `ecommerce`.

---

## FASE 7: Dashboard de Estadísticas (Panel Admin — Propietario)
**Objetivo:** Darle al propietario una vista clara del negocio sin que tenga que interpretar tablas técnicas. No requiere cambios al esquema de base de datos; se construye enteramente sobre `ventas` y `venta_detalles`.

- [ ] **Paso 7.1: `ReporteService`:** Centralizar en `app/Services/ReporteService.php` las consultas de agregación necesarias (ventas por período, producto más vendido por cantidad y por monto, vendedor con más ventas/clientes atendidos, ventas por sucursal, por método de pago, por origen, categorías/marcas más vendidas, alertas de stock bajo según `stock_minimo`). Todas deben aceptar un rango de fechas y, opcionalmente, filtro por sucursal.
- [ ] **Paso 7.2: Widgets en Filament (panel `admin`):**
    - Widgets tipo "Stats Overview" para las tarjetas rápidas (total vendido, número de ventas, ticket promedio, comparación vs. período anterior).
    - Widgets tipo "Chart" para los rankings y distribuciones (producto más vendido, vendedor top, ventas por sucursal, por método de pago).
    - Filtro global de rango de fechas en el dashboard (hoy/semana/mes/personalizado).
- [ ] **Paso 7.3: Alcance por panel:** Estas estadísticas globales viven únicamente en el panel `admin`. Los paneles `pos` y `almacen` no las muestran, salvo que más adelante se decida dar a Vendedor/Almacenero indicadores acotados a su propia sucursal o almacén.
