<?php
// App\Filament\Auth\EditProfile.php
//Explicacion vreve de este archivo
// este archivo permite editar el perfil del usuario
// se diferencia del EditProfile original de Filament en que usa nombres y apellidos en vez de name
// y permite editar el perfil en los 3 paneles (admin, pos, almacen)
declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    protected function getNombresFormComponent(): Component
    {
        return TextInput::make('nombres')
            ->label('Nombres')
            ->required()
            ->maxLength(150)
            ->autofocus();
    }

    protected function getApellidosFormComponent(): Component
    {
        return TextInput::make('apellidos')
            ->label('Apellidos')
            ->required()
            ->maxLength(150);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNombresFormComponent(),
                $this->getApellidosFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
