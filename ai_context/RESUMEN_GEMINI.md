# CONTEXTO DEL PROYECTO Y GUÍA DE TRABAJO COLABORATIVO (IA + DESARROLLO INTENCIONAL)

---

## 1. RESUMEN DEL PROYECTO Y ESTADO ACTUAL

* **Sistema:** Sistema de Ventas e Inventario para Librería (Robusto y Escalable).
* **Fase Actual (MVP):** El enfoque actual es un Producto Mínimo Viable. Las notificaciones complejas (WhatsApp) se posponen para una Fase 2. Todo el control de traspasos y alertas de stock será interno/manual mediante el panel de Filament en esta primera etapa.
* **Lógicas de Negocio Críticas Implementadas:**
  * **Pre-venta en Cola:** Los vendedores arman la canasta y la dejan "pendiente". El cajero solo busca el "cliente temporal" y cobra.
  * **Precios Escalonados:** Los precios varían automáticamente (Unidad, Docena, Por Mayor) según la cantidad vendida.
  * **Plantillas Escolares:** Creación de listas de útiles predefinidas por colegio/curso para ventas rápidas.
  * **Jerarquía de Sucursales:** El sistema soporta múltiples ubicaciones físicas, cada una con su propia tienda y depósito interno.
* **Stack Tecnológico Principal:** Laravel 13, PostgreSQL, Filament v5. Entorno de desarrollo ensamblado en Cursor.

---

## 2. STACK DE IA Y DISTRIBUCIÓN DE ROLES

El trabajo está dividido estratégicamente para garantizar código limpio y aprendizaje por parte del desarrollador humano:

                      ┌───────────────────────────────────────────┐
                      │    ROLES Y FLUJO DE TRABAJO DE LA IA      │
                      └─────────────────────┬─────────────────────┘
                                            │
             ┌──────────────────────────────┴──────────────────────────────┐
             ▼                                                             ▼
┌─────────────────────────────────┐                           ┌─────────────────────────────────┐
│     GEMINI PRO (ESTRATEGA)      │                           │      CLAUDE PRO (OPERATIVO)     │
│  (Arquitectura & Aprendizaje)   │                           │     (Generador de Código)       │
├─────────────────────────────────┤                           ├─────────────────────────────────┤
│ • Planificación y BD            │                           │ • Generación de migraciones y   │
│ • Diseño de flujos reales       │                           │   código en Laravel/Filament. │
│ • Explicación paso a paso       │                           │ • Entregar código listo y       │
│ • Definición de MVP             │                           │   depuración estricta.        │
└─────────────────────────────────┘                           └─────────────────────────────────┘

### Rol B: Claude Pro — *Agente Operativo de Generación*
* **Foco:** Ejecución directa, creación de módulos y código limpio.
* **Tareas específicas:**
  * Crear modelos, migraciones, Services y Actions basándose ESTRICTAMENTE en el esquema `DATABASE.md`.
  * Mantener el código limpio siguiendo los estándares de Laravel definidos en `CLAUDE.md`.

---

## 3. FILOSOFÍA Y METODOLOGÍA DE DESARROLLO (APRENDIZAJE ACTIVO)

**Objetivo principal:** Desarrollar el sistema progresivamente garantizando que el desarrollador entienda el **100% de la lógica implementada**.

### Reglas de interacción obligatorias para la IA:
1. **Explicación Previa o Acompañada:** Antes o durante la sugerencia de un módulo o bloque de código clave, la IA debe explicar *qué hace*, *por qué se eligió esa estructura* y *cómo se relaciona con Laravel*.
2. **Desarrollo Incremental (Paso a Paso):** NO generar módulos gigantescos de una sola vez. Avanzar siempre en esta secuencia por cada módulo:
   * **Paso 1:** Migraciones basadas en `DATABASE.md`.
   * **Paso 2:** Modelos y Relaciones de Eloquent.
   * **Paso 3:** Lógica de Negocio (Services/Actions).
   * **Paso 4:** Recursos de Filament (Paneles, Tablas, Formularios).
   * **Paso 5:** Pruebas y validación manual por parte del desarrollador.

# CONTEXTO DEL PROYECTO Y GUÍA DE TRABAJO COLABORATIVO (IA + DESARROLLO INTENCIONAL)

---

## 1. RESUMEN DEL PROYECTO Y ESTADO ACTUAL

* **Sistema:** Sistema de Ventas e Inventario para Librería (Backend unificado, múltiples frontends).
* **Arquitectura de Interfaces:** 
  * Panel de administración interno y POS operado vía **Filament v5** (PHP/Laravel).
  * Catálogo público/E-commerce operado vía aplicación independiente en **React** conectada por API al mismo motor.
* **Fase Actual (MVP):** El enfoque actual es levantar el núcleo del sistema. Las notificaciones externas (WhatsApp) se posponen. El control de traspasos y stock será interno mediante el panel de Filament.
* **Lógicas de Negocio Críticas Implementadas:**
  * **Pre-venta en Cola (POS):** Vendedores arman la canasta (`vendedor_id`), el cajero cobra y el cliente se retira.
  * **Omnicanalidad:** El stock se descuenta en tiempo real sin importar si la venta (`origen`) es POS o E-commerce.
  * **Pagos Reales:** Soporte para referencias de pago bancario y transacciones QR.
  * **Precios Escalonados:** Los precios varían automáticamente (Unidad, Docena, Mayor).
  * **Plantillas Escolares:** Listas de útiles predefinidas para ventas ultra rápidas.

---

## 2. STACK DE IA Y DISTRIBUCIÓN DE ROLES

El trabajo está dividido estratégicamente para garantizar código limpio y aprendizaje activo:

### Rol B: Claude Pro — *Agente Operativo de Generación*
* **Foco:** Ejecución directa, creación de módulos y código limpio.
* **Tareas específicas:**
  * Crear modelos, migraciones, Services y Actions basándose ESTRICTAMENTE en el esquema `DATABASE.md`.
  * Mantener el código limpio siguiendo los estándares de Laravel definidos en `CLAUDE.md`.

---

## 3. FILOSOFÍA Y METODOLOGÍA DE DESARROLLO (APRENDIZAJE ACTIVO)

**Objetivo principal:** Desarrollar el sistema progresivamente garantizando que el desarrollador entienda el **100% de la lógica implementada** para su futuro manejo Full-Stack.

### Reglas de interacción obligatorias para la IA:
1. **Explicación Previa:** Antes o durante la sugerencia de un módulo, la IA debe explicar *qué hace*, *por qué se eligió esa estructura* y *cómo se relaciona con el stack Laravel*.
2. **Desarrollo Incremental:** NO generar módulos gigantes de una sola vez. Avanzar en esta secuencia:
   * **Paso 1:** Migraciones basadas en `DATABASE.md`.
   * **Paso 2:** Modelos y Relaciones de Eloquent.
   * **Paso 3:** Lógica de Negocio (Services/Actions).
   * **Paso 4:** Endpoints API / Recursos de Filament.
   * **Paso 5:** Pruebas y validación manual.







