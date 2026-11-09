<?php

namespace Database\Factories;

use App\Enums\IdeaStatus;
use App\Models\Category;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Idea>
 */
class IdeaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(6),
            'problem' => fake()->paragraph(3),
            'proposal' => fake()->paragraph(3),
            'expected_impact' => fake()->optional()->sentence(),
            'is_anonymous' => false,
            'status' => IdeaStatus::New,
            'votes_count' => 0,
            'submitted_at' => now(),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }

    public function status(IdeaStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
