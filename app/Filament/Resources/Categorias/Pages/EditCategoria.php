<?php

namespace App\Filament\Resources\Categorias\Pages;

use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Support\AccionesPapelera;
use App\Models\Categoria;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoria extends EditRecord
{
    protected static string $resource = CategoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AccionesPapelera::delete(
                fn (Categoria $categoria) => $categoria->tieneProductosActivos(),
                'Esta categoría tiene productos activos asociados. Reasígnalos a otra categoría primero (el campo es obligatorio en Producto).',
            ),
            AccionesPapelera::forceDeleteSeguro(),
            RestoreAction::make(),
        ];
    }
}
