<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\ConfiguracionNegocio;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ConfiguracionNegocioPage extends Page
{
    protected static ?string $title = 'Configuración de Negocio';

    protected static ?string $navigationLabel = 'Configuración de Negocio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(ConfiguracionNegocio::actual()
            ->only(['umbral_mayor', 'soles_por_punto', 'valor_por_punto']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormFields())
            ->statePath('data');
    }

    /**
     * @return array<Component>
     */
    protected function getFormFields(): array
    {
        return [
            TextInput::make('umbral_mayor')
                ->label('Umbral de Precio Mayorista (unidades)')
                ->helperText('Cantidad mínima de un ítem para activar el precio mayorista automáticamente. Debe ser mayor a 12 (umbral de Docena).')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(13),
            TextInput::make('soles_por_punto')
                ->label('Soles por punto de fidelización')
                ->helperText('Monto de compra necesario para que un cliente registrado gane 1 punto.')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(1),
            TextInput::make('valor_por_punto')
                ->label('Valor por punto (S/)')
                ->helperText('Descuento en soles que vale cada punto al momento de canjearse.')
                ->numeric()
                ->step(0.01)
                ->required()
                ->minValue(0),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('guardar')
                ->footer([
                    Actions::make([
                        Action::make('guardar')
                            ->label('Guardar cambios')
                            ->submit('guardar')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function guardar(): void
    {
        $data = $this->form->getState();

        ConfiguracionNegocio::actual()->update($data);

        Notification::make()
            ->success()
            ->title('Configuración actualizada')
            ->send();
    }
}
