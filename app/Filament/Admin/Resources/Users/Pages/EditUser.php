<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * An admin cannot take their own admin role away, and the last admin cannot be demoted;
     * both are enforced here (inside the transaction), not only in the form.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $role = Arr::pull($data, 'role');

        return DB::transaction(function () use ($record, $data, $role): User {
            if ($record->hasRole('admin') && $role !== 'admin') {
                if ($record->is(auth()->user())) {
                    throw ValidationException::withMessages([
                        'data.role' => 'You cannot remove your own admin role.',
                    ]);
                }

                if (User::role('admin')->lockForUpdate()->count() <= 1) {
                    throw ValidationException::withMessages([
                        'data.role' => 'The last remaining admin cannot be demoted.',
                    ]);
                }
            }

            $record->update($data);
            $record->syncRoles($role);

            return $record;
        });
    }
}
