<?php

namespace App\Policies;

use App\Actions\SaveRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Backs the role editor on /admin. Built-in roles can be edited (within the
 * guards in SaveRole) but never deleted; custom roles can be deleted once empty.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('users.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('users.manage')
            && ! in_array($role->name, SaveRole::BUILT_IN, true)
            && $role->users()->doesntExist();
    }
}
