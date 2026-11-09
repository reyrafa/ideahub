<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Actions\SaveRole;
use App\Filament\Admin\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Pre-checks the role's current permissions, group by group.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Role $role */
        $role = $this->getRecord();
        $current = $role->permissions()->pluck('name')->all();

        $data['granted'] = array_map(
            fn (array $permissions): array => array_values(array_intersect(array_keys($permissions), $current)),
            SaveRole::CATALOG,
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Role $record */
        return app(SaveRole::class)->handle($record, $data['name'] ?? null, $data['granted'] ?? []);
    }
}
