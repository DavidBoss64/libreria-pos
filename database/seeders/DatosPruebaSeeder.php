<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Inventario\RegistrarMovimientoInventarioAction;
use App\Enums\AlmacenTipo;
use App\Enums\TipoMovimientoInventario;
use App\Enums\UserRole;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de prueba realistas para desarrollar Fase 4 (Ventas POS) sobre una
 * organización y un catálogo ya poblados: 2 sucursales (cada una con su
 * almacén `tienda` auto-creado por `SucursalObserver`, ver Paso 3.5) + 1
 * depósito central compartido, un empleado por rol operativo, y 20 productos
 * con sus variantes y stock inicial.
 *
 * El stock inicial se registra con `RegistrarMovimientoInventarioAction`
 * (tipo 'ajuste', el mismo camino que usa la Page real del Paso 3.4) —
 * nunca se escribe `inventarios` directamente, para no romper el Kardex
 * (CLAUDE.md regla 5).
 *
 * Pensado para correr sobre una base de datos vacía (`php artisan migrate:fresh --seed`).
 * Re-ejecutarlo sobre datos existentes fallará por los `unique` de slug/código.
 */
class DatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $admin = $this->crearAdmin();
            [$sucursalCentral, $sucursalNorte] = $this->crearSucursales();

            // `SucursalObserver::created()` ya creó el almacén `tienda` de cada
            // sucursal (Paso 3.5) — se recupera aquí en vez de crearlo de nuevo.
            $tiendaCentral = $sucursalCentral->almacenes()->firstOrFail();
            $tiendaNorte = $sucursalNorte->almacenes()->firstOrFail();
            $depositoCentral = $this->crearDepositoCentral($sucursalCentral);

            $this->crearEmpleados($sucursalCentral, $sucursalNorte, $depositoCentral);

            $variantes = $this->crearCatalogo();

            $this->registrarStockInicial($variantes, $tiendaCentral, $tiendaNorte, $depositoCentral, $admin);
        });
    }

    private function crearAdmin(): User
    {
        return User::create([
            'nombres' => 'Rosa',
            'apellidos' => 'Huamán',
            'email' => 'admin@libreria.test',
            'password' => 'password',
            'role' => UserRole::Admin,
            'sucursal_id' => null,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: Sucursal, 1: Sucursal}
     */
    private function crearSucursales(): array
    {
        $central = Sucursal::create([
            'nombre' => 'Sucursal Central',
            'direccion' => 'Av. Grau 450, Ica',
            'estado' => true,
        ]);

        $norte = Sucursal::create([
            'nombre' => 'Sucursal Norte',
            'direccion' => 'Jr. Lima 210, Chincha Alta',
            'estado' => true,
        ]);

        return [$central, $norte];
    }

    /**
     * Depósito adicional (modelo "hub and spoke"): abastece por traspaso a
     * ambas sucursales, no solo a la que lo "posee" administrativamente.
     */
    private function crearDepositoCentral(Sucursal $sucursalDueña): Almacen
    {
        return Almacen::create([
            'sucursal_id' => $sucursalDueña->id,
            'nombre' => 'Depósito Central',
            'tipo' => AlmacenTipo::Deposito,
            'estado' => true,
        ]);
    }

    private function crearEmpleados(Sucursal $central, Sucursal $norte, Almacen $depositoCentral): void
    {
        $vendedorCentral = User::create([
            'nombres' => 'Luis',
            'apellidos' => 'Ramírez',
            'email' => 'vendedor.central@libreria.test',
            'password' => 'password',
            'role' => UserRole::Vendedor,
            'sucursal_id' => $central->id,
            'is_active' => true,
        ]);

        $cajeroCentral = User::create([
            'nombres' => 'María',
            'apellidos' => 'Torres',
            'email' => 'cajero.central@libreria.test',
            'password' => 'password',
            'role' => UserRole::Cajero,
            'sucursal_id' => $central->id,
            'is_active' => true,
        ]);

        $vendedorNorte = User::create([
            'nombres' => 'Jhon',
            'apellidos' => 'Quispe',
            'email' => 'vendedor.norte@libreria.test',
            'password' => 'password',
            'role' => UserRole::Vendedor,
            'sucursal_id' => $norte->id,
            'is_active' => true,
        ]);

        $cajeroNorte = User::create([
            'nombres' => 'Carla',
            'apellidos' => 'Flores',
            'email' => 'cajero.norte@libreria.test',
            'password' => 'password',
            'role' => UserRole::Cajero,
            'sucursal_id' => $norte->id,
            'is_active' => true,
        ]);

        $almacenero = User::create([
            'nombres' => 'Pedro',
            'apellidos' => 'Salazar',
            'email' => 'almacenero@libreria.test',
            'password' => 'password',
            'role' => UserRole::Almacenero,
            'sucursal_id' => null,
            'is_active' => true,
        ]);

        $almacenero->almacenes()->attach($depositoCentral->id);

        unset($vendedorCentral, $cajeroCentral, $vendedorNorte, $cajeroNorte);
    }

    /**
     * @return Collection<int, ProductoVariante>
     */
    private function crearCatalogo(): Collection
    {
        $marcas = collect(['Faber-Castell', 'Artesco', 'Standford', 'Pelikan'])
            ->mapWithKeys(fn (string $nombre) => [$nombre => Marca::create([
                'nombre' => $nombre,
                'slug' => Str::slug($nombre),
            ])]);

        $categorias = collect(['Cuadernos', 'Escritura', 'Arte y Color', 'Papelería', 'Oficina'])
            ->mapWithKeys(fn (string $nombre) => [$nombre => Categoria::create([
                'nombre' => $nombre,
                'slug' => Str::slug($nombre),
            ])]);

        // 20 productos reales de librería/papelería, con 1-3 variantes cada uno
        // (color/medida/tipo) para poder probar la cascada Producto → Variante.
        $catalogo = [
            ['nombre' => 'Cuaderno Universitario 100 Hojas', 'marca' => 'Standford', 'categoria' => 'Cuadernos', 'variantes' => [
                ['atributos' => ['tipo' => 'Cuadriculado'], 'costo' => 4.20],
                ['atributos' => ['tipo' => 'Rayado'], 'costo' => 4.20],
            ]],
            ['nombre' => 'Cuaderno Espiral 80 Hojas', 'marca' => 'Artesco', 'categoria' => 'Cuadernos', 'variantes' => [
                ['atributos' => ['medida' => 'A4'], 'costo' => 5.50],
                ['atributos' => ['medida' => 'A5'], 'costo' => 4.00],
            ]],
            ['nombre' => 'Lapicero Punta Fina 0.7mm', 'marca' => 'Faber-Castell', 'categoria' => 'Escritura', 'variantes' => [
                ['atributos' => ['color' => 'Azul'], 'costo' => 0.70],
                ['atributos' => ['color' => 'Negro'], 'costo' => 0.70],
                ['atributos' => ['color' => 'Rojo'], 'costo' => 0.70],
            ]],
            ['nombre' => 'Lápiz Triangular 2B', 'marca' => 'Faber-Castell', 'categoria' => 'Escritura', 'variantes' => [
                ['atributos' => [], 'costo' => 0.45],
            ]],
            ['nombre' => 'Borrador Blanco Suave', 'marca' => 'Artesco', 'categoria' => 'Escritura', 'variantes' => [
                ['atributos' => [], 'costo' => 0.35],
            ]],
            ['nombre' => 'Tajador Metálico Doble Uso', 'marca' => 'Artesco', 'categoria' => 'Escritura', 'variantes' => [
                ['atributos' => [], 'costo' => 0.55],
            ]],
            ['nombre' => 'Corrector Líquido 8ml', 'marca' => 'Pelikan', 'categoria' => 'Escritura', 'variantes' => [
                ['atributos' => [], 'costo' => 1.80],
            ]],
            ['nombre' => 'Resaltador Fluorescente', 'marca' => 'Faber-Castell', 'categoria' => 'Escritura', 'variantes' => [
                ['atributos' => ['color' => 'Amarillo'], 'costo' => 1.40],
                ['atributos' => ['color' => 'Verde'], 'costo' => 1.40],
                ['atributos' => ['color' => 'Rosado'], 'costo' => 1.40],
            ]],
            ['nombre' => 'Colores de Madera x12', 'marca' => 'Faber-Castell', 'categoria' => 'Arte y Color', 'variantes' => [
                ['atributos' => [], 'costo' => 5.80],
            ]],
            ['nombre' => 'Plumones Gruesos x12', 'marca' => 'Artesco', 'categoria' => 'Arte y Color', 'variantes' => [
                ['atributos' => [], 'costo' => 7.50],
            ]],
            ['nombre' => 'Témperas x6 Colores', 'marca' => 'Artesco', 'categoria' => 'Arte y Color', 'variantes' => [
                ['atributos' => [], 'costo' => 6.50],
            ]],
            ['nombre' => 'Plastilina x12 Colores', 'marca' => 'Standford', 'categoria' => 'Arte y Color', 'variantes' => [
                ['atributos' => [], 'costo' => 4.80],
            ]],
            ['nombre' => 'Papel Bond A4 75g', 'marca' => 'Standford', 'categoria' => 'Papelería', 'variantes' => [
                ['atributos' => ['presentacion' => 'Paquete x500'], 'costo' => 13.50],
            ]],
            ['nombre' => 'Cartulina Escolar', 'marca' => 'Standford', 'categoria' => 'Papelería', 'variantes' => [
                ['atributos' => ['color' => 'Blanca'], 'costo' => 0.30],
                ['atributos' => ['color' => 'Color'], 'costo' => 0.35],
            ]],
            ['nombre' => 'Folder Manila A4', 'marca' => 'Artesco', 'categoria' => 'Papelería', 'variantes' => [
                ['atributos' => [], 'costo' => 0.40],
            ]],
            ['nombre' => 'Sobre Manila A4', 'marca' => 'Artesco', 'categoria' => 'Papelería', 'variantes' => [
                ['atributos' => [], 'costo' => 0.35],
            ]],
            ['nombre' => 'Regla Escolar 30cm', 'marca' => 'Faber-Castell', 'categoria' => 'Oficina', 'variantes' => [
                ['atributos' => [], 'costo' => 1.10],
            ]],
            ['nombre' => 'Tijera Escolar Punta Roma', 'marca' => 'Pelikan', 'categoria' => 'Oficina', 'variantes' => [
                ['atributos' => [], 'costo' => 3.20],
            ]],
            ['nombre' => 'Goma en Barra 21g', 'marca' => 'Pelikan', 'categoria' => 'Oficina', 'variantes' => [
                ['atributos' => [], 'costo' => 1.00],
            ]],
            ['nombre' => 'Clips Metálicos Caja x100', 'marca' => 'Standford', 'categoria' => 'Oficina', 'variantes' => [
                ['atributos' => [], 'costo' => 2.30],
            ]],
        ];

        $variantes = collect();
        $consecutivo = 0;

        foreach ($catalogo as $item) {
            $producto = Producto::create([
                'nombre' => $item['nombre'],
                'slug' => Str::slug($item['nombre']),
                'marca_id' => $marcas[$item['marca']]->id,
                'categoria_id' => $categorias[$item['categoria']]->id,
                'imagen_principal' => null,
                'estado' => true,
            ]);

            foreach ($item['variantes'] as $v) {
                $consecutivo++;
                $costo = $v['costo'];

                $variantes->push(ProductoVariante::create([
                    'producto_id' => $producto->id,
                    'codigo_barras' => '775' . str_pad((string) $consecutivo, 10, '0', STR_PAD_LEFT),
                    'codigo_interno' => 'PRD-' . str_pad((string) $consecutivo, 5, '0', STR_PAD_LEFT),
                    'atributos' => $v['atributos'] === [] ? null : $v['atributos'],
                    'costo_real' => $costo,
                    'precio_venta_unidad' => round($costo * 1.30, 2),
                    'precio_venta_docena' => round($costo * 1.15, 2),
                    'precio_venta_mayor' => round($costo * 1.10, 2),
                    'estado' => true,
                ]));
            }
        }

        return $variantes;
    }

    /**
     * @param Collection<int, ProductoVariante> $variantes
     */
    private function registrarStockInicial(
        Collection $variantes,
        Almacen $tiendaCentral,
        Almacen $tiendaNorte,
        Almacen $depositoCentral,
        User $admin,
    ): void {
        $action = app(RegistrarMovimientoInventarioAction::class);

        foreach ($variantes as $indice => $variante) {
            // Cada 7ma variante queda deliberadamente en stock bajo en la
            // Tienda Central, para poder ver el resaltado rojo (Paso 3.6)
            // con datos reales sin tener que vender nada primero.
            $stockTiendaCentral = $indice % 7 === 0 ? 3 : random_int(10, 35);

            // Algunas variantes quedan sin stock en la Tienda Norte, para
            // tener casos reales de "hay que pedir traspaso al depósito".
            $stockTiendaNorte = $indice % 5 === 0 ? 0 : random_int(5, 20);

            $stockDepositoCentral = random_int(40, 100);

            foreach ([
                [$tiendaCentral, $stockTiendaCentral],
                [$tiendaNorte, $stockTiendaNorte],
                [$depositoCentral, $stockDepositoCentral],
            ] as [$almacen, $cantidad]) {
                if ($cantidad <= 0) {
                    continue;
                }

                $action->handle(
                    almacenId: $almacen->id,
                    productoVarianteId: $variante->id,
                    tipoMovimiento: TipoMovimientoInventario::Ajuste,
                    cantidad: $cantidad,
                    motivo: 'Stock inicial (datos de prueba)',
                    usuarioId: $admin->id,
                );
            }
        }
    }
}
