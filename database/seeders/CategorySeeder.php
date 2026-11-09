<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Category name => whether it is active.
     *
     * @var array<string, bool>
     */
    private const CATEGORIES = [
        'Cost Saving' => true,
        'Safety' => true,
        'Process' => true,
        'Workplace' => false,
        'Digital' => true,
    ];

    /**
     * Seed the demo categories (idempotent).
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $isActive) {
            Category::query()->updateOrCreate(['name' => $name], ['is_active' => $isActive]);
        }
    }
}
