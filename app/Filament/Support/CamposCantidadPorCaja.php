<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\ProductoVariante;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Campos reutilizables "Por unidad / Por caja" para capturar una cantidad de stock —
 * usado por el Ajuste Manual de Inventario (Paso 3.4/mejora sesión 2026-08-05) y por
 * Compras a Proveedores (Paso 5.2). El Kardex SIEMPRE recibe el total en unidades:
 * "caja" es solo un atajo de captura en la UI, nunca una unidad de stock nueva.
 *
 * Extraído a un componente compartido solo al llegar al segundo uso real (Compras) —
 * antes de eso, un solo uso no justificaba la abstracción.
 */
class CamposCantidadPorCaja
{
    /**
     * $permitirNegativo: true para Ajuste Manual (una cantidad negativa representa
     * merma/daño/pérdida); false para Compras (una compra siempre suma stock, nunca
     * tiene sentido una cantidad negativa en una línea de recepción).
     *
     * @return array<int, mixed>
     */
    public static function campos(bool $permitirNegativo = true): array
    {
        return [
            Radio::make('modo_cantidad')
                ->label('Ingresar cantidad')
                ->options([
                    'unidad' => 'Por unidad',
                    'caja' => 'Por caja',
                ])
                ->default('unidad')
                ->inline()
                ->live()
                ->dehydrated(),
            TextInput::make('cantidad')
                ->label($permitirNegativo ? 'Cantidad (+/-)' : 'Cantidad')
                ->integer()
                ->minValue($permitirNegativo ? null : 1)
                ->visible(fn (Get $get) => static::esModoUnidad($get))
                ->required(fn (Get $get) => static::esModoUnidad($get))
                ->rules(fn (Get $get) => (static::esModoUnidad($get) && $permitirNegativo) ? ['not_in:0'] : [])
                ->helperText($permitirNegativo
                    ? 'Usa un número positivo para un ingreso o corrección al alza, y uno negativo para una merma, daño o pérdida.'
                    : 'Cantidad de unidades que llegaron.'),
            TextInput::make('unidades_por_caja')
                ->label('Unidades por caja')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->live(onBlur: true)
                ->visible(fn (Get $get) => $get('modo_cantidad') === 'caja')
                ->required(fn (Get $get) => $get('modo_cantidad') === 'caja')
                ->helperText('Precargado desde el producto. Si lo cambias, se actualiza como el nuevo tamaño de caja por defecto de este producto.'),
            TextInput::make('cantidad_cajas')
                ->label($permitirNegativo ? 'Cantidad de cajas (+/-)' : 'Cantidad de cajas')
                ->integer()
                ->minValue($permitirNegativo ? null : 1)
                ->live(onBlur: true)
                ->visible(fn (Get $get) => $get('modo_cantidad') === 'caja')
                ->required(fn (Get $get) => $get('modo_cantidad') === 'caja')
                ->rules(fn (Get $get) => (($get('modo_cantidad') === 'caja') && $permitirNegativo) ? ['not_in:0'] : [])
                ->helperText($permitirNegativo
                    ? 'Usa un número negativo para retirar cajas (ej. dañadas). El total en unidades se calcula solo.'
                    : 'Cantidad de cajas que llegaron. El total en unidades se calcula solo.'),
            TextEntry::make('preview_total_unidades')
                ->hiddenLabel()
                ->visible(fn (Get $get) => $get('modo_cantidad') === 'caja')
                ->state(fn (Get $get) => static::previewTotalUnidades($get('cantidad_cajas'), $get('unidades_por_caja')))
                ->columnSpanFull(),
        ];
    }

    /**
     * Wire en el `afterStateUpdated` del Select de variante: precarga el tamaño de
     * caja ya guardado en el producto (o lo limpia si no tiene uno configurado).
     */
    public static function sincronizarUnidadesPorCaja(Set $set, mixed $productoVarianteId): void
    {
        $variante = filled($productoVarianteId) ? ProductoVariante::find($productoVarianteId) : null;
        $set('unidades_por_caja', $variante?->unidades_por_caja);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function esPorCaja(array $data): bool
    {
        return ($data['modo_cantidad'] ?? 'unidad') === 'caja';
    }

    /**
     * Total en unidades a partir de los datos ya enviados del formulario — lo único
     * que debe llegar al Kardex, nunca "cajas".
     *
     * @param  array<string, mixed>  $data
     */
    public static function cantidadFinalEnUnidades(array $data): int
    {
        return static::esPorCaja($data)
            ? (int) $data['cantidad_cajas'] * (int) $data['unidades_por_caja']
            : (int) $data['cantidad'];
    }

    /**
     * Si el admin/almacenero escribió un tamaño de caja distinto al ya guardado en el
     * producto, lo actualiza. Debe llamarse dentro de la misma `DB::transaction()` que
     * el movimiento de Kardex correspondiente — si el movimiento se rechaza, este
     * cambio tampoco debe quedar a medias.
     *
     * @param  array<string, mixed>  $data
     */
    public static function actualizarTamanoCajaSiCambio(array $data): void
    {
        if (! static::esPorCaja($data)) {
            return;
        }

        $variante = ProductoVariante::find($data['producto_variante_id']);
        $nuevoTamano = (int) $data['unidades_por_caja'];

        if ($variante !== null && $variante->unidades_por_caja !== $nuevoTamano) {
            $variante->update(['unidades_por_caja' => $nuevoTamano]);
        }
    }

    private static function esModoUnidad(Get $get): bool
    {
        return ($get('modo_cantidad') ?? 'unidad') === 'unidad';
    }

    private static function previewTotalUnidades(mixed $cantidadCajas, mixed $unidadesPorCaja): string
    {
        $cajas = (int) ($cantidadCajas ?? 0);
        $porCaja = (int) ($unidadesPorCaja ?? 0);

        if ($cajas === 0 || $porCaja <= 0) {
            return 'Ingresa cajas y unidades por caja para ver el total.';
        }

        return '= '.($cajas * $porCaja).' unidades';
    }
}
