<?php

namespace Database\Factories;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusChange>
 */
class StatusChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idea_id' => Idea::factory(),
            'from_status' => IdeaStatus::New,
            'to_status' => IdeaStatus::UnderReview,
            'note' => null,
            'changed_by' => User::factory(),
            'changed_at' => now(),
        ];
    }
}
