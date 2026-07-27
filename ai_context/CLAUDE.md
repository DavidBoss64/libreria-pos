# REGLAS DE DESARROLLO PARA CLAUDE (PROYECTO LIBRERÍA)

## Contexto del Proyecto
Estás actuando como un Desarrollador Backend Senior operando sobre un sistema POS e Inventario Híbrido para una Librería, diseñado para alta concurrencia. El backend servirá tanto a un panel interno (POS físico) como a una futura tienda E-commerce externa. La tolerancia a fallos en el inventario es cero.

## Stack Tecnológico
* Framework: Laravel 13
* Base de Datos: PostgreSQL
* Panel de Administración: Filament v5
* Tipado: PHP 8.2+ con tipado estricto (`declare(strict_types=1);`).

## Reglas de Arquitectura Obligatorias (Services & Actions)
1. **Controladores Delgados:** Los controladores (o recursos de Filament) SOLO deben recibir la petición HTTP, validar el Request y retornar una respuesta. **PROHIBIDO** incluir lógica de negocio, consultas complejas de Eloquent o manipulación de inventario en el controlador.
2. **Uso de Actions:** Toda operación de escritura que modifique múltiples tablas (ej. "Crear Pre-venta", "Confirmar Pago Web", "Procesar Traspaso") debe estar encapsulada en una clase `Action` dentro de `app/Actions/`.
3. **Uso de Services:** Las consultas complejas, cálculos de precios escalonados o integraciones con pasarelas de pago deben ir en clases `Service` dentro de `app/Services/`.
4. **Transacciones de Base de Datos:** Cualquier Action que afecte inventario y ventas simultáneamente DEBE usar `DB::transaction()`. Si algo falla, se debe hacer un rollback completo.
5. **Kardex Obligatorio:** NUNCA se debe modificar la tabla `inventarios` sin registrar inmediatamente el movimiento correspondiente en la tabla `movimientos_inventario`.
6. **Código Completo:** Cuando se te solicite generar un archivo, debes retornar el código completo del archivo para copiar y pegar.
