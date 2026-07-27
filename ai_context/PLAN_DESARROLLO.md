# PLAN DE DESARROLLO Y LÓGICA DE NEGOCIO (SISTEMA LIBRERÍA HÍBRIDO)

Este documento define la hoja de ruta del proyecto, dividida en Fases (Sprints). Cada fase debe completarse y probarse antes de iniciar la siguiente.

---

## FASE 1: Cimientos, Jerarquía y Autenticación
**Objetivo:** Levantar la base del sistema, configurar PostgreSQL y establecer quién usa el sistema y desde dónde.

*   **Paso 1.1: Setup Base:** Instalación de Laravel 13, configuración de la base de datos y despliegue del panel Filament v5.
*   **Paso 1.2: Migraciones y Modelos Base:** `sucursales`, `almacenes` y `users`.
*   **Paso 1.3: Lógica de Negocio (Jerarquía):**
    *   Un usuario (`User`) siempre pertenece a una `Sucursal` (excepto el Super Admin).
    *   Una `Sucursal` tiene al menos un `Almacen` (puede ser 'tienda' para ventas o 'deposito' para guardado).
*   **Paso 1.4: Interfaz:** Crear los Recursos en Filament para gestionar Sucursales, Almacenes y Usuarios con roles definidos (`admin`, `cajero`, `almacenero`, `vendedor`).

---

## FASE 2: Catálogo de Productos y Precios Dinámicos
**Objetivo:** Estructurar el inventario teórico (lo que se vende, no las cantidades aún) y la lógica de precios.

*   **Paso 2.1: Migraciones:** `marcas`, `categorias`, `productos` y `producto_variantes`.
*   **Paso 2.2: Lógica de Negocio (Precios Escalonados):**
    *   Un producto general (ej. "Cuaderno 100 hojas") tiene variantes (ej. "Rojo", "Azul").
    *   Cada variante maneja un `codigo_interno` (búsqueda rápida) y un `codigo_barras`.
    *   **Regla de Precios:** Se definen tres precios (`unidad`, `docena`, `mayor`). El sistema debe ser capaz de consultar el Service correspondiente para saber qué precio aplicar según la cantidad que el cliente lleve.
*   **Paso 2.3: Interfaz:** Formularios en Filament para crear productos con sus variantes y precios.

---

## FASE 3: Motor de Inventario y Traspasos (El Kardex)
**Objetivo:** Controlar el stock real. Esta es la parte más crítica del backend; tolerancia a fallos cero.

*   **Paso 3.1: Migraciones:** `inventarios`, `movimientos_inventario`, `traspasos`, `traspaso_detalles`.
*   **Paso 3.2: Lógica de Negocio (Kardex Estricto):**
    *   **Regla de Oro:** NUNCA se actualiza la tabla `inventarios` directamente. 
    *   Se debe crear una clase `InventoryAction` que reciba: el producto, el almacén, la cantidad y el motivo. Esta clase actualiza el stock y crea el registro inmutable en `movimientos_inventario`.
    *   **Traspasos:** Flujo manual donde un vendedor solicita stock al depósito. Cambia de estados (`solicitado` -> `en_transito` -> `completado`). Al completarse, el `InventoryAction` saca stock de un almacén y lo mete en otro.
*   **Paso 3.3: Interfaz:** Tablas en Filament para ver el stock actual y un panel para gestionar/aprobar traspasos.

> **NOTA ABIERTA (pendiente de decisión antes de implementar 3.1/3.2):**
> 1.  **Riesgo de sobreventa en Pre-venta:** el flujo actual (Fase 4) solo descuenta stock cuando el cajero *cierra* la venta, no cuando el vendedor arma la canasta pendiente. Si dos vendedores agregan el mismo último ítem a dos pre-ventas distintas, ambas pasan sin error hasta el cobro. Falta decidir: ¿el `InventoryAction` debe **reservar** stock al armar la pre-venta (requeriría una columna tipo `cantidad_reservada` en `inventarios`), o basta con **validar disponibilidad en el momento del cobro** y rechazar/ajustar ítems agotados? Es una decisión de negocio, no solo técnica.
> 2.  **Falta restricción única en `inventarios`:** `DATABASE.md` no define un índice único sobre `(almacen_id, producto_variante_id)`. Sin él, nada impide crear dos filas de stock para el mismo producto en el mismo almacén, lo que rompería la integridad del Kardex. Agregar esta restricción al definir la migración 3.1.

---

## FASE 4: Módulo de Ventas POS (Pre-venta)
**Objetivo:** El corazón de la tienda física. Flujo rápido para evitar filas largas.

*   **Paso 4.1: Migraciones:** `clientes`, `ventas`, `venta_detalles`.
*   **Paso 4.2: Lógica de Negocio (Flujo Pre-venta):**
    *   **Vendedor:** Atiende al cliente, arma la canasta en el sistema. Se crea una `venta` con estado `pendiente` y se le asigna un `cliente_temporal` (ej. "Juan Polera Roja").
    *   **Cajero:** Busca "Juan Polera Roja" en su pantalla. Confirma los productos.
    *   **Pago:** Se selecciona método (Efectivo, QR, etc.). Si es QR, se guarda el ID del banco en `referencia_pago`.
    *   **Cierre:** El sistema ejecuta el `InventoryAction` de la Fase 3, descuenta el stock y pasa la venta a `completado`.
*   **Paso 4.3: Interfaz:** Pantalla POS optimizada en Filament para Vendedores (armar pedido) y Cajeros (cobrar).

---

## FASE 5: Optimizaciones (Plantillas y Compras)
**Objetivo:** Agilizar procesos recurrentes de la librería.

*   **Paso 5.1: Plantillas Escolares:** Desarrollo de `listas_escolares`. Lógica para que un vendedor seleccione "2do Básico - San Calixto" y el sistema agregue 15 productos a la canasta de golpe.
*   **Paso 5.2: Compras a Proveedores:** Ingreso de mercadería nueva (`compras`, `proveedores`). Al confirmar una compra, el sistema inyecta stock a través de `InventoryAction`.
*   **Paso 5.3: Adelantos de Sueldo:** Un módulo simple (CRUD) para registrar dinero entregado a empleados antes de fin de mes.

---

## FASE 6: API y E-Commerce (El Frontend Externo)
**Objetivo:** Conectar la tienda web de React al núcleo de Laravel.

*   **Paso 6.1: API Endpoints:** Crear rutas de API protegidas para el catálogo de productos.
*   **Paso 6.2: Autenticación Web:** Permitir a los clientes iniciar sesión (usando Sanctum o JWT).
*   **Paso 6.3: Lógica de Carrito Web:** Cuando la app de React envía una orden confirmada, Laravel reutiliza EXACTAMENTE la misma lógica de creación de venta y descuento de stock (Fase 4 y Fase 3), pero marcando el `origen` como `ecommerce`.