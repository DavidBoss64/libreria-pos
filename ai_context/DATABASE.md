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
* sucursal_id: bigint (FK -> sucursales.id)
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
* sucursal_id: bigint (FK -> sucursales.id) (Nullable)
* is_active: boolean (Default: true)
* timestamps

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
* marca_id: bigint (FK -> marcas.id) (Nullable)
* categoria_id: bigint (FK -> categorias.id)
* estado: boolean (Default: true)
* timestamps, softDeletes

**Tabla: producto_variantes**
* id: bigint (PK)
* producto_id: bigint (FK -> productos.id)
* codigo_barras: varchar(100) (Unique, Nullable) (Para escáner)
* codigo_interno: varchar(100) (Unique) (Para búsqueda manual rápida)
* atributos: jsonb (Nullable)
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
* stock_minimo: integer (Default: 5)
* timestamps

**Tabla: movimientos_inventario** (Kardex / Auditoría Polimórfica)
* id: bigint (PK)
* almacen_id: bigint (FK -> almacenes.id)
* producto_variante_id: bigint (FK -> producto_variantes.id)
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
* traspaso_id: bigint (FK -> traspasos.id)
* producto_variante_id: bigint (FK -> producto_variantes.id)
* cantidad: integer
* timestamps

## 4. Ventas (Modelo de Pre-venta POS + E-commerce)
**Tabla: clientes**
* id: bigint (PK)
* nombre_completo: varchar(255)
* documento: varchar(20) (Nullable)
* email: varchar(150) (Unique, Nullable)
* password: varchar(255) (Nullable - Para inicio de sesión web)
* timestamps, softDeletes

**Tabla: ventas**
* id: bigint (PK)
* numero_ticket: varchar(20) (Unique)
* sucursal_id: bigint (FK -> sucursales.id)
* usuario_id: bigint (FK -> users.id) (Nullable - El cajero que cobra, null en web)
* vendedor_id: bigint (FK -> users.id) (Nullable - Quien armó la canasta, null en web)
* cliente_id: bigint (FK -> clientes.id) (Nullable - Cliente registrado)
* cliente_temporal: varchar(100) (Nullable - Nombre rápido para la cola de caja)
* total: decimal(10, 2)
* metodo_pago: enum ('efectivo', 'transferencia', 'tarjeta', 'qr') (Nullable hasta que se pague)
* referencia_pago: varchar(255) (Nullable) (ID de transacción de banco o pasarela)
* origen: enum ('pos', 'ecommerce') (Default: 'pos')
* estado: enum ('pendiente', 'esperando_pago', 'completado', 'anulado')
* timestamps

**Tabla: venta_detalles**
* id: bigint (PK)
* venta_id: bigint (FK -> ventas.id)
* producto_variante_id: bigint (FK -> producto_variantes.id)
* cantidad: integer
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
* lista_escolar_id: bigint (FK -> listas_escolares.id)
* producto_variante_id: bigint (FK -> producto_variantes.id)
* cantidad: integer
* timestamps

## 6. Compras y Proveedores
**Tabla: proveedores**
* id: bigint (PK)
* razon_social: varchar(255)
* nit_documento: varchar(50) (Nullable)
* contacto: varchar(150) (Nullable)
* telefono: varchar(50) (Nullable)
* estado: boolean (Default: true)
* timestamps, softDeletes

**Tabla: compras**
* id: bigint (PK)
* proveedor_id: bigint (FK -> proveedores.id)
* almacen_id: bigint (FK -> almacenes.id)
* usuario_id: bigint (FK -> users.id)
* numero_factura: varchar(100) (Nullable)
* total: decimal(10, 2)
* estado: enum ('completado', 'anulado')
* timestamps

**Tabla: compra_detalles**
* id: bigint (PK)
* compra_id: bigint (FK -> compras.id)
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
