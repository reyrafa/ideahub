<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'department' => $this->faker->randomElement(['Engineering', 'Sales', 'Support', 'HR', 'Finance']),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function employee(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::findOrCreate('employee')));
    }

    public function reviewer(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::findOrCreate('reviewer')));
    }

    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::findOrCreate('admin')));
    }
}
