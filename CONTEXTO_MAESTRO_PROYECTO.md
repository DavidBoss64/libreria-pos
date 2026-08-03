# CONTEXTO MAESTRO DEL PROYECTO — Librería POS/Inventario (Perú)

> **Propósito de este archivo:** este chat va a seguir creciendo con decisiones nuevas. Este documento es el "checkpoint" que resume TODO lo acordado hasta la fecha, para refrescarlo periódicamente (pídeme "regenera el CONTEXTO_MAESTRO" cada cierto tiempo, o cuando sientas que el chat ya está muy largo) y para arrancar sesiones nuevas — con Claude Code, con Gemini, o conmigo — sin perder nada.
>
> **Última actualización:** Fase 3 (Motor de Inventario y Traspasos) 100% completa y cerrada — incluyendo el Paso 3.4 (Ajuste Manual de Inventario) y la corrección del selector de almacén destino en traspasos. Siguiente: Fase 4.
>
> Este archivo es un RESUMEN. Los 5 archivos del proyecto (`CLAUDE.md`, `DATABASE.md`, `LOGICA_NEGOCIO.md`, `PLAN_DESARROLLO.md`, `RESUMEN_GEMINI.md`) siguen siendo la fuente de verdad completa y detallada — adjúntalos siempre junto con este. `CONTEXTO_HANDOFF_GEMINI.md` fue retirado (redundante con este archivo).

---

## 1. Qué es el proyecto

Sistema de Ventas e Inventario para una librería en **Perú**. Panel interno (POS físico) + futura tienda **E-commerce en React** por API. Propietario único que **no quiere complejidad administrativa innecesaria** — principio que ha guiado casi todas las decisiones de "simplicidad deliberada" de abajo.

**Stack:** Laravel 13, PostgreSQL, Filament v5, PHP 8.2+ estricto. Desarrollo con Claude Code (a veces vía el editor Antigravity).

---

## 2. Estado real de avance

- ✅ **Fase 1** completa (setup, migraciones base, jerarquía, multi-panel, **Paso 1.4.1** — autogestión de cuenta: perfil + recuperación de contraseña en los 3 paneles).
- ✅ **Fase 1.5** completa (Policies nativas, aislamiento por sucursal con `AislaPorSucursal`).
- ✅ **Fase 2** completa (catálogo: marcas/categorías/productos/variantes, `PrecioService` con Docena+Mayor, calculadora de margen, Policies de catálogo, imagen por URL con vista previa en vivo, vista de catálogo mejorada visualmente en panel `pos` sin exponer `costo_real`).
- ✅ **Fase 3 — 100% COMPLETA (3.1 a 3.4):**
  - `inventarios`, `movimientos_inventario`, `traspasos`, `traspaso_detalles`, `almacen_usuario`; `InventoryAction`/`RegistrarMovimientoInventarioAction` con `lockForUpdate()`.
  - `AislaPorAlmacen` (Trait nuevo) + Resources de Inventario/Traspaso en paneles `almacen` y `pos`, con `CompletarTraspasoAction`/`SolicitarTraspasoAction`.
  - Selector de almacén destino en traspasos **corregido**: ahora filtra por `tipo = 'tienda'` de la propia sucursal del Vendedor y muestra la sucursal en el label.
  - **Paso 3.4 — Ajuste Manual de Inventario:** Page en panel `admin` (`App\Filament\Pages\AjusteInventario`), único camino real para dar stock inicial antes de Compras (Fase 5). Suite completa: **34/34**.
  - Pendiente sin urgencia (no bloquea nada): panel `admin` sin vista global de solo lectura de Inventario/Traspaso, pese a que `LOGICA_NEGOCIO.md` define al Administrador con "visión panorámica". Se agrega cuando convenga.
  - Decisión documentada: el ciclo de traspaso lo conduce enteramente el Almacenero (sin confirmación de recepción separada en la sucursal destino) — reabrir solo si el negocio pide ese control adicional.
- ⏳ **Siguiente: Fase 4** (Ventas POS / Pre-venta), con dos sub-decisiones ya cerradas de antemano (ver sección 3): Configuración de Negocio editable (Paso 4.4) y Fidelización por puntos (Paso 4.5).
- Pendientes más adelante: Fase 5 (Plantillas/Compras — resolverá de forma definitiva el ingreso de stock), Fase 6 (API E-commerce), Fase 7 (Dashboard).
- **Datos de prueba (sesión 2026-08-03):** `database/seeders/DatosPruebaSeeder.php` deja la BD lista para probar Fase 4 con datos realistas — 2 sucursales + 1 depósito central, 6 usuarios (uno por rol, contraseña `password`, ver detalle en `PLAN_DESARROLLO.md`), 20 productos / 27 variantes con precios y stock inicial (vía `RegistrarMovimientoInventarioAction`, respetando el Kardex). Se corre con `php artisan migrate:fresh --seed`.
- **Nota técnica sin impacto funcional:** los 3 `PanelProvider` ahora usan `->viteTheme('resources/css/filament/theme.css')` (personalización visual estándar de Filament v5, sin overrides todavía) — ver detalle en `PLAN_DESARROLLO.md`.

**El checklist real y detallado con notas de implementación vive en `PLAN_DESARROLLO.md`** — este resumen no lo reemplaza.

---

## 3. Decisiones de arquitectura acumuladas (NO re-proponer sin razón nueva y concreta)

* **Multi-panel Filament:** tres paneles independientes — `admin`, `pos` (Vendedor+Cajero), `almacen` (Almacenero). Ningún rol se loguea en un panel ajeno (`canAccessPanel()`).
* **Policies nativas de Laravel, NO Spatie/Filament Shield:** 4 roles fijos y cerrados en `users.role` (enum).
* **Policies siempre completas (10 métodos):** nunca dejar métodos plurales (`deleteAny`, etc.) sin definir — Filament autoriza por default en modo no-estricto.
* **Dos dimensiones de aislamiento, cada una con su Trait:**
  * `AislaPorSucursal` — Vendedor/Cajero (panel `pos`), filtra por `sucursal_id`.
  * `AislaPorAlmacen` — Almacenero (panel `almacen`), filtra por almacenes asignados vía `almacen_usuario` (modelo "hub and spoke": un almacén central puede abastecer a varias sucursales — una sucursal, para efectos de stock, ES su propio almacén tipo `tienda`; un traspaso "sucursal a almacén" es siempre, en los datos, un traspaso almacén-a-almacén).
  * Ambos sobreescriben `getEloquentQuery()` a nivel de Resource — nunca Global Scope de Modelo.
* **Ciclo de traspaso conducido enteramente por el Almacenero** (sin confirmación de recepción separada en destino) — decisión del Paso 3.3, reabrir solo si el negocio lo pide.
* **Sin reserva dura de stock en pre-venta:** `cantidad_comprometida` (indicador no bloqueante) + `lockForUpdate()` real en el cobro + expiración automática de pre-ventas abandonadas (`ventas.expira_en`).
* **Sin control de caja (`turnos_caja`):** descartado para el MVP. Mejora opcional futura.
* **Sin factura electrónica/fiscal:** solo comprobante interno (`numero_ticket`, derivado del `id` autoincremental). Sin integración SUNAT — **nota de salud del sistema:** recomendar al propietario confirmar con su contador si su régimen tributario lo obliga a algo distinto, no es algo que este chat pueda confirmar con certeza legal.
* **API de e-commerce con Laravel Sanctum**, no JWT.
* **`origen` de ventas:** único par válido `('pos', 'ecommerce')`.
* **Precio Mayorista:** automático por umbral de cantidad O manual (Vendedor/Cajero fuerza para cliente recurrente). Umbral en `configuracion_negocio.umbral_mayor` (ver abajo).
* **Margen de ganancia editable:** calculadora bidireccional (precio↔margen) en el catálogo del panel `admin`, sin columnas nuevas.
* **`unidad_medida` / cantidades decimales — decisión cerrada, NO implementar:** todo producto se modela como pieza/rollo/pliego entero; `atributos` (jsonb) describe color/medida/presentación libremente. Se reabre solo si aparece un producto real que deba venderse fraccionado a pedido.
* **Imagen de producto por URL externa:** `imagen_principal` se llena pegando una URL (ej. Cloudinary), con vista previa en vivo. No usa `FileUpload` ni `storage:link`.
* **Vista de catálogo en panel `pos`:** el Vendedor/Cajero NUNCA ve `costo_real` ni margen — solo los 3 precios de venta.
* **Ajuste Manual de Inventario (Fase 3.4):** solo `admin` puede ajustar stock manualmente por ahora — la variante "Almacenero con previa autorización" mencionada en `LOGICA_NEGOCIO.md` queda fuera de alcance hasta que el negocio lo pida explícitamente.
* **Fidelización por puntos (Fase 4, Paso 4.5, diseño cerrado):** 1 punto por cada `soles_por_punto` (default 30) de compra; cada punto vale `valor_por_punto` (default S/ 0.30, ~1% de retorno) de descuento directo en soles — no porcentaje. Solo clientes registrados (`cliente_id`) acumulan, no `cliente_temporal`. Kardex de puntos (`movimientos_puntos`) con el mismo principio que el de inventario.
* **Configuración de Negocio editable por el admin (Fase 4, Paso 4.4):** `umbral_mayor`, `soles_por_punto`, `valor_por_punto` viven en tabla `configuracion_negocio` (fila única) editable desde una Page de Filament en `admin` — NO en archivos `config/*.php`. Retrofita el `umbral_mayor` de Fase 2.
* **Generación de identificadores secuenciales:** siempre derivado de un ID autoincremental o secuencia de Postgres con bloqueo atómico — nunca "último valor + 1" consultado en la app.
* **Slugs con `unique(ignoreRecord: true)`** en todo formulario de edición con campo slug.
* **Pruebas de concurrencia — dos niveles, no uno, con patrón técnico ya implementado (CLAUDE.md regla 17):** invariante de negocio en la suite normal (SQLite) + prueba de bloqueo de fila real contra Postgres usando **dos procesos PHP independientes** (`Symfony\Component\Process\Process`, script standalone en `tests/concurrency/`), en un grupo `#[Group('concurrency')]` excluido del run por defecto, corrido aparte con `php artisan test --group=concurrency`. Ya usado en Fase 3, se repite en Fase 4 (cierre de venta, canje de puntos).
* **Kardex Obligatorio aplica también a puntos de fidelización:** mismo principio que inventario — nunca modificar un saldo (`inventarios.cantidad` o `clientes.puntos_acumulados`) sin registrar el movimiento correspondiente.
* **Dashboard de estadísticas (Fase 7):** producto más vendido, vendedor top, ventas por sucursal/método de pago/origen, alertas de stock bajo — vía `ReporteService` + Widgets nativos de Filament, sin tablas nuevas.

---

## 4. Patrón de trabajo establecido en este proyecto

1. **Un paso a la vez**, nunca una fase completa de golpe.
2. **Plan antes de código:** Claude Code muestra el plan de archivos/comandos antes de ejecutar; se confirma antes de proceder.
3. **Verificación manual (o Tinker/test) antes de marcar un paso como completado.**
4. **Checklist de `PLAN_DESARROLLO.md` como única fuente de verdad de progreso** — se actualiza al cerrar cada paso, con notas de implementación (no solo el checkbox).
5. **Ambigüedades de negocio se debaten ANTES de tocar código**, se resuelven con una decisión explícita, y se documentan — nunca se dejan "sobreentendidas".
6. **Huecos entre teoría (`LOGICA_NEGOCIO.md`) y plan técnico (`PLAN_DESARROLLO.md`)** se cierran agregando un paso accionable, no solo un comentario suelto (así se corrigieron: permisos por rol, aislamiento por sucursal, aislamiento por almacén, Ajuste Manual de Inventario, y el selector de traspaso mal filtrado).
7. **No se construye para casos hipotéticos** sin confirmar que son reales (`turnos_caja`, `unidad_medida`) — pero si el costo de diseñar para flexibilidad futura es bajo y la necesidad es plausible, se construye la versión flexible desde el inicio (ej. `almacen_usuario` como pivote).
8. **`/clear` en Claude Code entre fases** (o al cerrar sub-pasos grandes), siempre re-adjuntando los `.md` actualizados al retomar, con un prompt que pida diagnóstico de estado antes de escribir código.
9. **Revisión periódica de salud del sistema:** cuando se acumulan varias fases, vale la pena un repaso honesto contra estándares de la industria (Kardex, RBAC, WMS) para confirmar que no hay drift silencioso — se hizo una vez ya, con resultado positivo.
10. **Sincronización periódica de archivos:** cada cierto tiempo, subir los `.md` reales del proyecto a este chat para comparar contra la copia canónica y corregir drift en ambas direcciones (avances no documentados aquí, o documentos como `RESUMEN_GEMINI.md`/este archivo que se quedan atrás si no se actualizan explícitamente).

---

## 5. Cómo usar este archivo

* **Este es el único documento de este tipo** — `CONTEXTO_HANDOFF_GEMINI.md` quedó retirado.
* Úsalo solo para abrir chats nuevos con IAs que **no sean Claude Code** — Claude Code se orienta con `CLAUDE.md` + `PLAN_DESARROLLO.md`.
* Adjúntalo junto con los 5 `.md` del proyecto al abrir esos chats nuevos.
* Pide que se lea completo antes de proponer o decidir nada.
* Pide "regenera el CONTEXTO_MAESTRO_PROYECTO.md" cada vez que se acumulen varias decisiones nuevas, o sube los `.md` reales del proyecto para una sincronización completa (ver punto 10 de la sección 4).
