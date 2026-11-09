<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Actions\SaveRole;
use App\Filament\Admin\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveRole::class)->handle(null, $data['name'] ?? null, $data['granted'] ?? []);
    }
}
