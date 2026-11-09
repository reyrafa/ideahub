<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Role name => permissions granted to it.
     *
     * @var array<string, array<int, string>>
     */
    private const MATRIX = [
        'employee' => [
            'ideas.create',
            'ideas.vote',
            'comments.create',
        ],
        'reviewer' => [
            'ideas.create',
            'ideas.vote',
            'comments.create',
            'ideas.change-status',
            'comments.hide',
            'comments.view-hidden',
        ],
        'admin' => [
            'ideas.create',
            'ideas.vote',
            'comments.create',
            'ideas.change-status',
            'comments.hide',
            'comments.view-hidden',
            'ideas.view-anonymous-author',
            'ideas.manage-any',
            'categories.manage',
            'users.manage',
        ],
    ];

    /**
     * Seed the roles and permissions (idempotent).
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MATRIX as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            foreach ($permissions as $permission) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            }

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
