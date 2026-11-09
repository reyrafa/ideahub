<?php

namespace Database\Factories;

use App\Models\WeeklySummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklySummary>
 */
class WeeklySummaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $end = now()->startOfDay();

        return [
            'period_start' => $end->subDays(7)->toDateString(),
            'period_end' => $end->toDateString(),
            'summary' => $this->faker->paragraph(),
            'ideas_count' => $this->faker->numberBetween(1, 20),
            'model' => 'claude-sonnet-5-5',
        ];
    }
}
