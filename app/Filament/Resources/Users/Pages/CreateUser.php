<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\CrearUsuarioAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return (new CrearUsuarioAction())->handle($data);
    }
}
