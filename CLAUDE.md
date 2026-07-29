# REGLAS DE DESARROLLO PARA CLAUDE (PROYECTO LIBRERÍA)

## Contexto del Proyecto
Estás actuando como un Desarrollador Backend Senior operando sobre un sistema POS e Inventario Híbrido para una Librería (Perú), diseñado para alta concurrencia. El backend servirá tanto a un panel interno (POS físico) como a una futura tienda E-commerce externa. La tolerancia a fallos en el inventario es cero.

**Nota fiscal:** El sistema NO emite factura electrónica/fiscal. Las ventas generan un comprobante interno (ticket) únicamente. No se debe implementar integración con SUNAT ni campos de facturación electrónica salvo que se indique lo contrario explícitamente en una fase futura.

## Stack Tecnológico
* Framework: Laravel 13
* Base de Datos: PostgreSQL
* Panel de Administración: Filament v5
* Tipado: PHP 8.2+ con tipado estricto (`declare(strict_types=1);`).

## Documentos de Referencia (consultar según la tarea)
Este archivo (`CLAUDE.md`) contiene solo reglas de arquitectura y estilo. Para todo lo demás, consulta explícitamente:
* **`DATABASE.md`** → esquema completo de tablas, columnas, tipos e índices. Fuente de verdad para migraciones.
* **`LOGICA_NEGOCIO.md`** → reglas de negocio, roles, flujos (pre-venta, traspasos, precios). Fuente de verdad para Actions/Services.
* **`PLAN_DESARROLLO.md`** → roadmap por fases con checklist de progreso (`- [ ]` / `- [x]`). **Revisar siempre al iniciar una sesión** para saber en qué paso continuar, y **actualizar los checkboxes al terminar** cada paso completado.

No asumas contenido de estos archivos de memoria de una sesión anterior: si no están mencionados en el mensaje actual, pide que se referencien antes de generar código que dependa de ellos.

## Arquitectura de Paneles Filament (MULTI-PANEL — OBLIGATORIO)
El sistema usa **tres paneles Filament independientes**, no uno solo con permisos ocultos. Esto es una decisión de arquitectura firme, no una preferencia estética:

1. **Panel `admin`** (prefijo `/admin`): Administrador/Gerencia. Acceso global: catálogo maestro, reportes, usuarios, sucursales, almacenes, auditoría.
2. **Panel `pos`** (prefijo `/pos`): Vendedor y Cajero. Solo recursos de pre-venta, cobro y consulta de stock de su sucursal.
3. **Panel `almacen`** (prefijo `/almacen`): Almacenero. Solo recepción de mercadería, gestión de stock bruto, inventarios físicos y despacho de traspasos.

**Reglas:**
* Cada `PanelProvider` registra ÚNICAMENTE los `Resource`, `Page` y `Widget` que corresponden a los roles de ese panel. No se comparten clases de `Resource` entre paneles aunque el modelo Eloquent subyacente sea el mismo; si dos paneles necesitan ver el mismo modelo con reglas distintas, se crean `Resource` separados (ej. `App\Filament\Pos\Resources\VentaResource` y `App\Filament\Admin\Resources\VentaResource`), cada uno con su propio `form()`, `table()` y `Policy`.
* El modelo `User` implementa `canAccessPanel(Panel $panel): bool` para restringir el **login** por rol y panel, no solo la visibilidad de menú. Un `vendedor` no debe poder autenticarse en `/almacen` bajo ninguna circunstancia.
* Aun dentro de un mismo panel (ej. `pos` con Vendedor y Cajero compartiéndolo), usar `Policies` de Laravel para separar acciones: el Vendedor puede crear/editar pre-ventas `pendiente`; el Cajero puede transicionarlas a `completado` y registrar pago. Ningún controlador/resource debe permitir ambas acciones al mismo rol.

## Reglas de Arquitectura Obligatorias (Services & Actions)
1. **Controladores Delgados:** Los controladores (o recursos de Filament) SOLO deben recibir la petición HTTP, validar el Request y retornar una respuesta. **PROHIBIDO** incluir lógica de negocio, consultas complejas de Eloquent o manipulación de inventario en el controlador.
2. **Uso de Actions:** Toda operación de escritura que modifique múltiples tablas (ej. "Crear Pre-venta", "Confirmar Pago Web", "Procesar Traspaso") debe estar encapsulada en una clase `Action` dentro de `app/Actions/`.
3. **Uso de Services:** Las consultas complejas, cálculos de precios escalonados o integraciones con pasarelas de pago deben ir en clases `Service` dentro de `app/Services/`.
4. **Transacciones de Base de Datos:** Cualquier Action que afecte inventario y ventas simultáneamente DEBE usar `DB::transaction()`. Si algo falla, se debe hacer un rollback completo.
5. **Kardex Obligatorio:** NUNCA se debe modificar la tabla `inventarios` sin registrar inmediatamente el movimiento correspondiente en la tabla `movimientos_inventario`.
6. **Bloqueo de fila en operaciones de stock:** Todo `InventoryAction` que descuente stock (cierre de venta, despacho de traspaso) DEBE usar `lockForUpdate()` sobre la fila de `inventarios` dentro de la transacción, para evitar condiciones de carrera entre cajeros/almaceneros concurrentes.
7. **Código Completo:** Cuando se te solicite generar un archivo, debes retornar el código completo del archivo para copiar y pegar.
8. **Autorización por Policies nativas (NO Spatie):** El control de acceso por rol se implementa con `Policies` de Laravel basadas en el campo `users.role` (enum fijo de 4 valores). NO instalar `spatie/laravel-permission` ni `Filament Shield` salvo que se indique explícitamente lo contrario en `PLAN_DESARROLLO.md` — ver decisión documentada en la Fase 1.5.
9. **Aislamiento multi-tenant obligatorio (dos dimensiones distintas):** El sistema tiene dos ejes de aislamiento de datos, cada uno con su propio Trait reutilizable — no confundirlos ni mezclarlos:
    * **Por sucursal** (`AislaPorSucursal`): aplica a Resources de negocio que ve Vendedor/Cajero en el panel `pos` (ej. `Venta`), filtrando por `sucursal_id` del usuario autenticado.
    * **Por almacén** (`AislaPorAlmacen`): aplica a Resources de negocio que ve el Almacenero en el panel `almacen` (ej. `Traspaso`), filtrando por los almacenes asignados al usuario vía la tabla pivote `almacen_usuario` — NO por `sucursal_id` (el Almacenero puede gestionar un almacén que abastece a varias sucursales a la vez, modelo "hub and spoke").
    Ambos Traits sobreescriben `getEloquentQuery()` a nivel de Resource de Filament (no Global Scope de Modelo), por la misma razón: no romper Jobs/Commands/Services que corren sin usuario autenticado. El rol `admin` está exento de ambos y ve todo.
10. **Pruebas obligatorias para lógica crítica:** Todo `Action` que modifique `inventarios` (venta, compra, traspaso, ajuste) debe entregarse junto con al menos una prueba Pest/PHPUnit que verifique el comportamiento esperado. Cuando el Action pueda ejecutarse concurrentemente (cierre de venta, despacho de traspaso), incluir un caso de prueba que simule dos operaciones simultáneas sobre el mismo `producto_variante_id` + `almacen_id`.
11. **Generación de identificadores secuenciales (`numero_ticket` y similares):** Nunca generar un número secuencial "legible" (ej. `numero_ticket`) consultando el último valor y sumando 1 en la aplicación — es una condición de carrera bajo concurrencia. Preferir derivarlo del ID autoincremental de la fila (ej. `'V-' . str_pad($venta->id, 6, '0', STR_PAD_LEFT)`) o, si se necesita un formato con reinicio periódico, usar una secuencia dedicada de PostgreSQL con bloqueo atómico.
12. **Autenticación de API (Fase 6):** Usar **Laravel Sanctum** para la autenticación de clientes vía la tienda web en React. No usar JWT — Sanctum es la opción nativa y más simple para este caso (SPA/app propia consumiendo la misma API), sin la complejidad adicional de gestión de tokens firmados que trae JWT.
13. **Policies completas, nunca parciales:** Toda `Policy` debe definir explícitamente los 10 métodos de habilidad estándar (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, `deleteAny`, `forceDeleteAny`, `restoreAny`), aunque varios compartan la misma condición. **Nunca** omitir un método asumiendo que Filament lo deniega por defecto: en modo no-estricto (el default), una `ability` sin método definido en la Policy se resuelve como `allow()`, no como `deny()`. Esto es especialmente crítico para Resources con `DeleteBulkAction`/`ForceDeleteBulkAction`/`RestoreBulkAction` en su Tabla, que dependen de los métodos plurales (`deleteAny`, etc.).
14. **Slugs únicos con `ignoreRecord`:** Todo campo `slug` con restricción `unique` en base de datos (marcas, categorias, productos, y cualquier tabla futura con slug) debe validarse en el formulario de Filament con `->unique(ignoreRecord: true)` (o el equivalente vigente en Filament v5). Sin esto, editar un registro sin cambiar el campo que genera el slug rompe con un error crudo de SQL en vez de un mensaje de validación.
15. **`FileUpload` requiere `storage:link` verificado:** Todo campo de tipo `FileUpload` en Filament (imágenes de producto, y cualquier adjunto futuro) debe venir acompañado de la confirmación explícita de que existe el enlace simbólico de almacenamiento (`php artisan storage:link`). Sin él, el archivo se guarda correctamente en la base de datos pero nunca se puede visualizar — falla en silencio, sin error visible.
