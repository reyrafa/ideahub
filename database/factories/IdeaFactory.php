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

    /**
     * An Implemented idea with recorded results (F12).
     */
    public function implemented(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IdeaStatus::Implemented,
            'hours_saved' => fake()->randomFloat(1, 1, 200),
            'money_saved' => fake()->randomFloat(2, 100, 50000),
            'results_note' => fake()->sentence(),
            'results_recorded_by' => User::factory(),
            'results_recorded_at' => now(),
        ]);
    }

    public function status(IdeaStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
