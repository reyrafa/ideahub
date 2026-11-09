<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts: email => [name, department, role]. The password is always "password".
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const USERS = [
        'employee@demo.test' => ['Emma Employee', 'Engineering', 'employee'],
        'reviewer@demo.test' => ['Ravi Reviewer', 'Operations', 'reviewer'],
        'reviewer2@demo.test' => ['Rosa Reviewer', 'Operations', 'reviewer'],
        'admin@demo.test' => ['Adam Admin', 'IT', 'admin'],
        'liam@demo.test' => ['Liam Carter', 'Engineering', 'employee'],
        'olivia@demo.test' => ['Olivia Bennett', 'Sales', 'employee'],
        'noah@demo.test' => ['Noah Schmidt', 'Support', 'employee'],
        'ava@demo.test' => ['Ava Martins', 'HR', 'employee'],
        'lucas@demo.test' => ['Lucas Oliveira', 'Finance', 'employee'],
        'mia@demo.test' => ['Mia Tanaka', 'Engineering', 'employee'],
        'ethan@demo.test' => ['Ethan Brooks', 'Sales', 'employee'],
        'sofia@demo.test' => ['Sofia Rossi', 'Support', 'employee'],
        'jack@demo.test' => ['Jack Novak', 'Finance', 'employee'],
        'zoe@demo.test' => ['Zoe Laurent', 'HR', 'employee'],
        'owen@demo.test' => ['Owen Hughes', 'Engineering', 'employee'],
        'chloe@demo.test' => ['Chloe Dubois', 'Sales', 'employee'],
        'leo@demo.test' => ['Leo Fischer', 'Support', 'employee'],
        'nina@demo.test' => ['Nina Kowalski', 'Finance', 'employee'],
    ];

    /**
     * Seed 15 employees, 2 reviewers and 1 admin (idempotent).
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        foreach (self::USERS as $email => [$name, $department, $role]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'department' => $department,
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
