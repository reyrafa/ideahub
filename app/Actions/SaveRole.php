<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates or updates a role and syncs exactly the given (catalogued) permissions.
 *
 * Enforced here, server-side, regardless of what the form submits:
 * built-in roles keep their name, and the admin role always keeps users.manage.
 */
class SaveRole
{
    /**
     * Roles seeded by RolesAndPermissionsSeeder that can be neither renamed nor deleted.
     *
     * @var array<int, string>
     */
    public const BUILT_IN = ['employee', 'reviewer', 'admin'];

    /**
     * The permission that must never be removed from the admin role (lockout protection).
     */
    public const LOCKOUT_PERMISSION = 'users.manage';

    /**
     * Seeded permissions an admin may grant, grouped for display: group => [permission => label].
     *
     * @var array<string, array<string, string>>
     */
    public const CATALOG = [
        'Ideas' => [
            'ideas.create' => 'Submit ideas',
            'ideas.vote' => 'Vote on ideas',
            'ideas.change-status' => 'Change idea status (review)',
            'ideas.view-anonymous-author' => 'See authors of anonymous ideas',
            'ideas.manage-any' => 'Manage any idea',
        ],
        'Comments' => [
            'comments.create' => 'Post comments',
            'comments.hide' => 'Hide comments',
            'comments.view-hidden' => 'View hidden comments',
        ],
        'Administration' => [
            'categories.manage' => 'Manage categories',
            'users.manage' => 'Manage users and roles',
        ],
    ];

    /**
     * @param  array<int|string, mixed>  $permissions  permission names, possibly nested by group
     */
    public function handle(?Role $role, ?string $name, array $permissions): Role
    {
        $granted = $this->normalize($permissions);

        return DB::transaction(function () use ($role, $name, $granted): Role {
            if ($role !== null && in_array($role->name, self::BUILT_IN, true)) {
                if ($name !== null && $name !== $role->name) {
                    throw ValidationException::withMessages([
                        'data.name' => 'Built-in roles cannot be renamed.',
                    ]);
                }

                $name = $role->name;
            }

            if ($name === null || $name === '') {
                throw ValidationException::withMessages(['data.name' => 'A role name is required.']);
            }

            if ($name === 'admin' && ! in_array(self::LOCKOUT_PERMISSION, $granted, true)) {
                throw ValidationException::withMessages([
                    'data.granted' => 'The admin role must keep "Manage users and roles" so admins cannot lock themselves out.',
                ]);
            }

            $role ??= new Role(['guard_name' => 'web']);
            $role->name = $name;
            $role->save();

            $role->syncPermissions(
                Permission::query()->where('guard_name', 'web')->whereIn('name', $granted)->get(),
            );

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * Flattens, de-duplicates and restricts the input to catalogued permissions.
     *
     * @param  array<int|string, mixed>  $permissions
     * @return array<int, string>
     */
    private function normalize(array $permissions): array
    {
        $allowed = array_merge(...array_map('array_keys', array_values(self::CATALOG)));

        $flat = [];
        array_walk_recursive($permissions, function (mixed $value) use (&$flat): void {
            if (is_string($value)) {
                $flat[] = $value;
            }
        });

        return array_values(array_intersect(array_unique($flat), $allowed));
    }
}
