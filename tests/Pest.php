<?php

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A valid payload for SubmitIdea / UpdateIdea, backed by a real active category.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validIdeaData(array $overrides = []): array
{
    return array_merge([
        'title' => 'A perfectly fine idea title',
        'category_id' => Category::factory()->create()->id,
        'problem' => str_repeat('p', 40),
        'proposal' => str_repeat('s', 40),
        'expected_impact' => 'Saves everyone some time.',
        'is_anonymous' => false,
    ], $overrides);
}

/**
 * Run the callback and return the validation errors it raised (empty if it did not throw).
 *
 * @return array<string, array<int, string>>
 */
function validationErrorsFrom(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}
