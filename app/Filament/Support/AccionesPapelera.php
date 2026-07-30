<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Helper reutilizable para las acciones de papelera de los Resources de
 * catálogo/estructura (Producto, Marca, Categoria, Almacen, Sucursal) —
 * ver PLAN_DESARROLLO.md Paso 3.7. Evita repetir el mismo try/catch y la
 * misma validación de negocio en cada Resource por separado.
 */
class AccionesPapelera
{
    /**
     * `ForceDeleteAction` (registro individual) NUNCA captura la excepción de
     * Postgres por defecto (a diferencia de `ForceDeleteBulkAction`, que sí lo
     * hace de fábrica) — sin esto, intentar eliminar en definitiva un registro
     * con historial protegido por `restrictOnDelete()` revienta como una
     * página de error cruda en vez de una notificación clara.
     */
    public static function forceDeleteSeguro(): ForceDeleteAction
    {
        return ForceDeleteAction::make()
            ->action(function (Model $record, ForceDeleteAction $action): void {
                try {
                    $record->forceDelete();
                    $action->success();
                } catch (QueryException) {
                    Notification::make()
                        ->title('No se puede eliminar permanentemente')
                        ->body('Este registro tiene historial relacionado (ventas, movimientos de inventario, traspasos, compras, etc.) que debe conservarse para el Kardex/auditoría. Puede quedarse en la papelera, pero no eliminarse en definitiva.')
                        ->danger()
                        ->send();

                    $action->halt();
                }
            });
    }

    /**
     * `DeleteAction` (envío individual a la papelera) con una validación de
     * negocio previa. $bloqueadoSi recibe el registro y devuelve true si NO
     * se debe permitir enviarlo a la papelera todavía.
     */
    public static function delete(Closure $bloqueadoSi, string $motivo): DeleteAction
    {
        return DeleteAction::make()
            ->before(function (Model $record, DeleteAction $action) use ($bloqueadoSi, $motivo): void {
                if ($bloqueadoSi($record)) {
                    Notification::make()
                        ->title('No se puede enviar a la papelera')
                        ->body($motivo)
                        ->danger()
                        ->send();

                    $action->halt();
                }
            });
    }

    /**
     * Igual que `delete()`, pero para el envío masivo a la papelera desde la
     * tabla. Bloquea la operación completa (no parcial) si algún registro
     * seleccionado no cumple la condición — más simple y predecible que un
     * cumplimiento parcial silencioso para una operación de catálogo.
     */
    public static function deleteBulk(Closure $bloqueadoSi, string $motivo): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->before(function (DeleteBulkAction $action) use ($bloqueadoSi, $motivo): void {
                if ($action->getSelectedRecords()->contains($bloqueadoSi)) {
                    Notification::make()
                        ->title('No se puede enviar a la papelera')
                        ->body($motivo)
                        ->danger()
                        ->send();

                    $action->halt();
                }
            });
    }
}
