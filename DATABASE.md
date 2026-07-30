# DICCIONARIO DE DATOS Y ESQUEMA RELACIONAL (PostgreSQL) - VERSIÓN DEFINITIVA

## 1. Autenticación, Usuarios y Sucursales
**Tabla: sucursales** (Jerarquía principal)
* id: bigint (PK)
* nombre: varchar(150) (Ej: "Sucursal Central")
* direccion: varchar(255) (Nullable)
* estado: boolean (Default: true)
* timestamps, softDeletes

**Tabla: almacenes**
* id: bigint (PK)
* sucursal_id: bigint (FK -> sucursales.id, indexado)
* nombre: varchar(150)
* tipo: enum ('tienda', 'deposito')
* estado: boolean (Default: true)
* timestamps, softDeletes

**Tabla: users**
* id: bigint (PK)
* nombres: varchar(150)
* apellidos: varchar(150)
* email: varchar(255) (Unique)
* password: varchar(255)
* role: enum ('admin', 'cajero', 'almacenero', 'vendedor')
* sucursal_id: bigint (FK -> sucursales.id, Nullable, indexado) — usado por Vendedor/Cajero. Para Almacenero, normalmente `null` (su asignación real es vía `almacen_usuario`, ver abajo).
* is_active: boolean (Default: true)
* timestamps

**Tabla: almacen_usuario** (Pivote — asignación de Almacenero(s) a Almacén(es), muchos a muchos)
* id: bigint (PK)
* usuario_id: bigint (FK -> users.id, indexado)
* almacen_id: bigint (FK -> almacenes.id, indexado)
* timestamps
* **Restricción:** `unique(['usuario_id', 'almacen_id'])` — evita asignar el mismo almacén dos veces al mismo usuario.
* **Nota de diseño:** se modela como muchos-a-muchos desde el inicio (aunque hoy se asigne un solo almacén por Almacenero) porque `LOGICA_NEGOCIO.md` contempla que un Almacenero pueda gestionar más de un almacén a futuro — construir la tabla intermedia ahora es barato; migrar desde una columna única después no lo es.

## 2. Catálogo (Productos y Precios Escalonados)
**Tabla: marcas**
* id: bigint (PK)
* nombre: varchar(150)
* slug: varchar(150) (Unique)
* timestamps, softDeletes

**Tabla: categorias**
* id: bigint (PK)
* nombre: varchar(150)
* slug: varchar(150) (Unique)
* timestamps, softDeletes

**Tabla: productos**
* id: bigint (PK)
* nombre: varchar(255)
* slug: varchar(255) (Unique)
* marca_id: bigint (FK -> marcas.id, Nullable, indexado)
* categoria_id: bigint (FK -> categorias.id, indexado)
* imagen_principal: varchar(255) (Nullable) — URL de una imagen alojada externamente (ej. Cloudinary u otro servicio en la nube), NO un archivo subido al servidor del proyecto. El formulario de Filament usa un campo de texto validado (`->url()`) con vista previa en vivo, no `FileUpload`. Un solo campo simple, sin galería múltiple, para no sobre-diseñar el MVP.
* estado: boolean (Default: true)
* timestamps, softDeletes

**Tabla: producto_variantes**
* id: bigint (PK)
* producto_id: bigint (FK -> productos.id, indexado)
* codigo_barras: varchar(100) (Unique, Nullable) (Para escáner)
* codigo_interno: varchar(100) (Unique) (Para búsqueda manual rápida)
* atributos: jsonb (Nullable) — Descriptivo únicamente (ej. `{"color": "rojo", "medida": "1 metro"}`); no afecta cálculos de precio ni de stock. Todo producto se vende y se controla como pieza/rollo/pliego entero (cantidad siempre `integer`) — decisión tomada, ver `PLAN_DESARROLLO.md` Fase 2.
* costo_real: decimal(10, 2) (Costo base + Transporte)
* precio_venta_unidad: decimal(10, 2) (+30% aprox)
* precio_venta_docena: decimal(10, 2) (+15% aprox)
* precio_venta_mayor: decimal(10, 2) (+10% aprox)
* estado: boolean (Default: true)
* timestamps, softDeletes

## 3. Inventario y Traspasos (Control Manual)
**Tabla: inventarios** (Stock actual)
* id: bigint (PK)
* almacen_id: bigint (FK -> almacenes.id)
* producto_variante_id: bigint (FK -> producto_variantes.id)
* cantidad: integer (Default: 0)
* cantidad_comprometida: integer (Default: 0) — Suma indicativa de unidades presentes en pre-ventas `pendiente`/`esperando_pago` activas para este producto+almacén. **No bloquea la venta**, es solo un indicador visual para el Vendedor ("quedan 5, hay 2 comprometidas"). Se recalcula/ajusta al crear, cancelar o completar una pre-venta.
* stock_minimo: integer (Default: 5)
* timestamps
* **Restricción:** `unique(['almacen_id', 'producto_variante_id'])` — OBLIGATORIA. Sin ella nada impide crear dos filas de stock para el mismo producto en el mismo almacén, rompiendo la integridad del Kardex.

**Tabla: movimientos_inventario** (Kardex / Auditoría Polimórfica)
* id: bigint (PK)
* almacen_id: bigint (FK -> almacenes.id, indexado)
* producto_variante_id: bigint (FK -> producto_variantes.id, indexado)
* tipo_movimiento: enum ('ingreso', 'salida', 'ajuste')
* cantidad: integer
* saldo_despues: integer
* motivo: varchar(100)
* referencia_tipo: varchar(255) (Nullable)
* referencia_id: bigint (Nullable)
* usuario_id: bigint (FK -> users.id)
* created_at: timestamp

**Tabla: traspasos** (Solicitudes manuales de vendedores a almacén)
* id: bigint (PK)
* almacen_origen_id: bigint (FK -> almacenes.id)
* almacen_destino_id: bigint (FK -> almacenes.id)
* estado: enum ('solicitado', 'preparando', 'en_transito', 'completado', 'cancelado')
* usuario_solicitante_id: bigint (FK -> users.id) (Vendedor)
* usuario_procesador_id: bigint (FK -> users.id) (Nullable) (Almacenero)
* timestamps

**Tabla: traspaso_detalles**
* id: bigint (PK)
* traspaso_id: bigint (FK -> traspasos.id, indexado)
* producto_variante_id: bigint (FK -> producto_variantes.id)
* cantidad: integer — cantidad solicitada.
* cantidad_preparada: integer (Nullable) — Paso 3.6. Cuánto reunió realmente el Almacenero para esta línea (puede ser menor a `cantidad` si no había stock suficiente). Solo editable mientras el traspaso está en estado `preparando`. `null` mientras no ha sido revisada. Para avanzar el traspaso a `en_transito`, TODAS las líneas deben tener este campo no-nulo (aunque sea `0`). `CompletarTraspasoAction` mueve stock usando este valor (con fallback a `cantidad` para traspasos creados antes de este campo).
* timestamps

## 4. Ventas (Modelo de Pre-venta POS + E-commerce)
**Tabla: clientes**
* id: bigint (PK)
* nombres: varchar(255)
* apellidos: varchar(255)
* documento: varchar(20) (Nullable)
* email: varchar(150) (Unique, Nullable)
* password: varchar(255) (Nullable - Para inicio de sesión web)
* telefono: varchar(50) (Nullable)
* puntos_acumulados: integer (Default: 0) — Saldo actual de puntos de fidelización (Fase 4). Análogo a `inventarios.cantidad`: NUNCA se modifica directamente, siempre a través de un registro en `movimientos_puntos`.
* timestamps, softDeletes

**Tabla: movimientos_puntos** (Kardex de fidelización — mismo principio que `movimientos_inventario`)
* id: bigint (PK)
* cliente_id: bigint (FK -> clientes.id, indexado)
* venta_id: bigint (FK -> ventas.id, Nullable, indexado) — nula solo para ajustes manuales
* tipo: enum ('ganado', 'canjeado', 'ajuste')
* puntos: integer (positivo para 'ganado', negativo para 'canjeado'/ajustes de resta)
* saldo_despues: integer
* usuario_id: bigint (FK -> users.id, Nullable) — quien procesó el ajuste manual (null si fue automático desde una venta)
* created_at: timestamp

**Tabla: ventas**
* id: bigint (PK)
* numero_ticket: varchar(20) (Unique) — Comprobante interno; el sistema NO emite factura electrónica/fiscal (decisión de negocio para Perú). **Generación:** derivado del `id` autoincremental de la venta (ej. `V-000123`), nunca de un contador consultado y sumado en la aplicación, para evitar duplicados bajo cobros concurrentes.
* sucursal_id: bigint (FK -> sucursales.id, indexado)
* usuario_id: bigint (FK -> users.id) (Nullable - El cajero que cobra, null en web)
* vendedor_id: bigint (FK -> users.id) (Nullable - Quien armó la canasta, null en web)
* cliente_id: bigint (FK -> clientes.id) (Nullable - Cliente registrado)
* cliente_temporal: varchar(100) (Nullable - Nombre rápido para la cola de caja)
* total: decimal(10, 2)
* metodo_pago: enum ('efectivo', 'transferencia', 'tarjeta', 'qr') (Nullable hasta que se pague)
* referencia_pago: varchar(255) (Nullable) (ID de transacción de banco o pasarela)
* origen: enum ('pos', 'ecommerce') (Default: 'pos')
* estado: enum ('pendiente', 'esperando_pago', 'completado', 'anulado') (indexado)
* expira_en: timestamp (Nullable) — Fecha límite para que una pre-venta `pendiente` siga activa. Un job programado marca como `cancelado` las pre-ventas vencidas para liberar `cantidad_comprometida` en `inventarios`.
* puntos_ganados: integer (Nullable, Default: 0) — Fidelización (Fase 4). Calculado al completar la venta según `config('fidelizacion.soles_por_punto')`. Solo aplica si `cliente_id` no es nulo (un `cliente_temporal` sin registro no puede acumular puntos).
* puntos_utilizados: integer (Nullable, Default: 0) — Puntos canjeados por el cliente en esta venta, si decidió aplicarlos.
* descuento_por_puntos: decimal(10, 2) (Nullable, Default: 0) — Monto de descuento resultante del canje (`puntos_utilizados * config('fidelizacion.valor_por_punto')`), ya reflejado en `total`.
* timestamps

**Tabla: venta_detalles**
* id: bigint (PK)
* venta_id: bigint (FK -> ventas.id, indexado)
* producto_variante_id: bigint (FK -> producto_variantes.id, indexado)
* cantidad: integer
* tipo_precio_aplicado: enum ('unidad', 'docena', 'mayor') — Registra qué nivel de precio se usó, para reportería (independiente de si el precio cambia después).
* precio_unitario: decimal(10, 2) (Se aplicará unidad, docena o mayor)
* subtotal: decimal(10, 2)
* timestamps

## 5. Listas Escolares (Plantillas Reutilizables)
**Tabla: listas_escolares**
* id: bigint (PK)
* nombre_plantilla: varchar(255) (Ej: "1ro Básico - Colegio San Calixto")
* colegio: varchar(255) (Nullable)
* precio_total_estimado: decimal(10, 2)
* es_plantilla: boolean (Default: true)
* timestamps, softDeletes

**Tabla: lista_escolar_detalles**
* id: bigint (PK)
* lista_escolar_id: bigint (FK -> listas_escolares.id, indexado)
* producto_variante_id: bigint (FK -> producto_variantes.id)
* cantidad: integer
* timestamps

## 6. Compras y Proveedores
**Tabla: proveedores**
* id: bigint (PK)
* razon_social: varchar(255)
* nit_documento: varchar(50) (Nullable) — RUC en el caso de Perú
* contacto: varchar(150) (Nullable)
* telefono: varchar(50) (Nullable)
* estado: boolean (Default: true)
* timestamps, softDeletes

**Tabla: compras**
* id: bigint (PK)
* proveedor_id: bigint (FK -> proveedores.id, indexado)
* almacen_id: bigint (FK -> almacenes.id, indexado)
* usuario_id: bigint (FK -> users.id)
* numero_factura: varchar(100) (Nullable) — Factura del proveedor recibida (esto SÍ aplica, es la compra que le hacen a la librería, no lo que la librería emite)
* total: decimal(10, 2)
* estado: enum ('completado', 'anulado')
* timestamps

**Tabla: compra_detalles**
* id: bigint (PK)
* compra_id: bigint (FK -> compras.id, indexado)
* producto_variante_id: bigint (FK -> producto_variantes.id)
* cantidad: integer
* precio_compra_unitario: decimal(10, 2)
* subtotal: decimal(10, 2)
* timestamps

## 7. Recursos Humanos y Finanzas
**Tabla: adelantos_sueldo**
* id: bigint (PK)
* usuario_id: bigint (FK -> users.id) (El empleado que recibe)
* registrado_por_id: bigint (FK -> users.id) (Quien entrega el dinero)
* monto: decimal(10, 2)
* fecha_adelanto: date
* estado: enum ('pendiente_descuento', 'descontado')
* timestamps

## 8. Control de Caja (FUERA DEL MVP)
**Decisión:** se descarta para el MVP. El negocio es de un solo propietario que no requiere arqueo formal de efectivo; toda la estadística de "cuánto se cobró en efectivo" se obtiene directamente de `ventas.metodo_pago` sin necesidad de una tabla adicional. Si en el futuro el negocio crece (más cajeros, sospecha de faltantes), se puede reintroducir una tabla `turnos_caja` (apertura/cierre + diferencia declarada vs. calculada) sin afectar el resto del esquema.

## 9. Configuración del Negocio (Fase 4)
**Tabla: configuracion_negocio** (fila única, siempre `id = 1` — no es un catálogo, es la configuración editable del negocio)
* id: bigint (PK)
* umbral_mayor: integer (Default: 24) — Migrado desde `config/precios.php` (Fase 2). Cantidad mínima para activar Precio Mayorista automáticamente (ver `LOGICA_NEGOCIO.md` sección 4).
* soles_por_punto: integer (Default: 30) — Fidelización (Fase 4, sección 9 de `LOGICA_NEGOCIO.md`). Monto de compra necesario para ganar 1 punto.
* valor_por_punto: decimal(10, 2) (Default: 0.30) — Valor en soles de descuento por cada punto canjeado.
* timestamps

**Decisión de diseño:** estos valores viven en una tabla de base de datos editable desde una **Page** dedicada de Filament en el panel `admin` (no un Resource — no se "crean" ni "eliminan" configuraciones, solo se edita la única fila existente), en vez de archivos `config/*.php`. Motivo: el propietario necesita poder ajustar estos números (ej. una promoción de doble puntos, o cambiar el umbral de mayorista) sin depender de un desarrollador ni de un despliegue de código. Se accede vía un modelo `ConfiguracionNegocio` con un accessor cacheado (ej. `ConfiguracionNegocio::actual()`), invalidando la caché automáticamente al guardar cambios desde el panel.
