# CONTEXTO DEL PROYECTO Y GUÍA DE TRABAJO COLABORATIVO (IA + DESARROLLO INTENCIONAL)

---

## 1. RESUMEN DEL PROYECTO Y ESTADO ACTUAL

* **Sistema:** Sistema de Ventas e Inventario para Librería — Perú (Backend unificado, múltiples frontends).
* **Arquitectura de Interfaces:**
  * Panel de administración interno y POS operado vía **Filament v5** (PHP/Laravel), dividido en **tres paneles independientes**: `admin` (Administrador/Gerencia), `pos` (Vendedor + Cajero) y `almacen` (Almacenero). No es un panel único con permisos ocultos.
  * Catálogo público/E-commerce operado vía aplicación independiente en **React** conectada por API al mismo motor.
* **Comprobantes:** El sistema NO emite factura electrónica/fiscal (no hay integración con SUNAT). Cada venta genera un `numero_ticket` interno de control. Decisión de negocio confirmada, no un vacío pendiente.
* **Fase Actual (MVP):** El enfoque actual es levantar el núcleo del sistema. Las notificaciones externas (WhatsApp) se posponen. El control de traspasos y stock será interno mediante los paneles de Filament.
* **Estado de avance:** Fase 1 completa (incluyendo Paso 1.4.1: autogestión de cuenta — perfil y recuperación de contraseña en los 3 paneles). Fase 1.5 completa (Policies nativas + Trait de aislamiento por sucursal). **Fase 2 completa** (catálogo: marcas, categorías, productos y variantes; `PrecioService` con precios escalonados y umbral configurable de mayorista; calculadora de margen bidireccional en el panel `admin`; catálogo read-only en el panel `pos`). **Siguiente:** Fase 3 (Motor de Inventario y Traspasos), que incluye una decisión nueva — modelo "hub and spoke" para que un Almacenero gestione uno o varios almacenes vía tabla pivote `almacen_usuario`, en vez de por sucursal.
* **Lógicas de Negocio Críticas Implementadas (en diseño):**
  * **Pre-venta en Cola (POS):** Vendedores arman la canasta (`vendedor_id`), el cajero cobra y el cliente se retira. Sin reserva dura de stock; validación con bloqueo de fila al momento del cobro, más expiración automática de pre-ventas abandonadas.
  * **Omnicanalidad:** El stock se descuenta en tiempo real sin importar si la venta (`origen`) es `pos` o `ecommerce` (valores unificados en todos los documentos).
  * **Pagos Reales:** Soporte para referencias de pago bancario y transacciones QR.
  * **Precios Escalonados:** Los precios varían automáticamente (Unidad, Docena, Mayor), y se registra qué nivel se aplicó en cada venta para reportería. Mayorista se activa por umbral de cantidad configurable (`config/precios.php`) **o** por selección manual del Vendedor/Cajero para clientes recurrentes.
  * **Catálogo con calculadora de margen:** el panel `admin` muestra en vivo el margen de ganancia (bidireccional: precio → margen o margen → precio) frente al costo real de cada variante, sin columnas nuevas en base de datos.
  * **Plantillas Escolares:** Listas de útiles predefinidas para ventas ultra rápidas.
  * **Dashboard de Estadísticas (panel `admin`):** producto más vendido, vendedor con más ventas/clientes atendidos, ventas por sucursal/método de pago/origen, alertas de stock bajo. Construido sobre `ventas` y `venta_detalles`, sin necesidad de módulos adicionales.
  * **Nota:** se evaluó y se descartó un módulo de apertura/cierre de caja (arqueo de efectivo) para el MVP — el propietario no requiere esa capa de auditoría por ahora. Queda como mejora futura opcional.
  * **Decisión cerrada:** no se implementa `unidad_medida` ni `cantidad` decimal. Todo producto se modela como pieza/rollo/pliego entero, usando `atributos` (jsonb) para describir color/medida/presentación. Se reabre solo si aparece un producto real que deba venderse fraccionado a pedido.

---

## 2. STACK DE IA Y DISTRIBUCIÓN DE ROLES

El trabajo está dividido estratégicamente para garantizar código limpio y aprendizaje activo:

### Rol B: Claude Pro — *Agente Operativo de Generación*
* **Foco:** Ejecución directa, creación de módulos y código limpio.
* **Tareas específicas:**
  * Crear modelos, migraciones, Services y Actions basándose ESTRICTAMENTE en el esquema `DATABASE.md`.
  * Mantener el código limpio siguiendo los estándares de Laravel definidos en `CLAUDE.md`, incluyendo la arquitectura multi-panel de Filament.

---

## 3. FILOSOFÍA Y METODOLOGÍA DE DESARROLLO (APRENDIZAJE ACTIVO)

**Objetivo principal:** Desarrollar el sistema progresivamente garantizando que el desarrollador entienda el **100% de la lógica implementada** para su futuro manejo Full-Stack.

### Reglas de interacción obligatorias para la IA:
1. **Explicación Previa:** Antes o durante la sugerencia de un módulo, la IA debe explicar *qué hace*, *por qué se eligió esa estructura* y *cómo se relaciona con el stack Laravel*.
2. **Desarrollo Incremental:** NO generar módulos gigantes de una sola vez. Avanzar en esta secuencia:
   * **Paso 1:** Migraciones basadas en `DATABASE.md`.
   * **Paso 2:** Modelos y Relaciones de Eloquent.
   * **Paso 3:** Lógica de Negocio (Services/Actions).
   * **Paso 4:** Endpoints API / Recursos de Filament (en el panel correspondiente: `admin`, `pos` o `almacen`).
   * **Paso 5:** Pruebas y validación manual.

DAVID