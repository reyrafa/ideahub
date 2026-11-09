<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Backs Shield's role resource on /admin as a read-only screen: roles and their
 * permissions are managed by RolesAndPermissionsSeeder only.
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
        return false;
    }

    public function update(User $user, Role $role): bool
    {
        return false;
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }
}
