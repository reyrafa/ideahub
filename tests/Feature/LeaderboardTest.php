<?php

use App\Actions\TopContributors;
use App\Enums\IdeaStatus;
use App\Filament\Widgets\LeaderboardWidget;
use App\Models\Idea;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00:00'));
    $this->employee = User::factory()->employee()->create();
});

function qualifyingIdea(User $user, array $overrides = []): Idea
{
    return Idea::factory()->for($user, 'author')->status(IdeaStatus::Approved)->create($overrides);
}

/**
 * @return array<string, int>
 */
function leaderboard(): array
{
    return app(TopContributors::class)->query()->get()->pluck('qualifying_ideas_count', 'name')->all();
}

it('F10 counts only Approved and Implemented ideas', function () {
    $user = User::factory()->create(['name' => 'Ann']);
    qualifyingIdea($user);
    Idea::factory()->for($user, 'author')->status(IdeaStatus::Implemented)->create();
    foreach ([IdeaStatus::New, IdeaStatus::UnderReview, IdeaStatus::Rejected] as $status) {
        Idea::factory()->for($user, 'author')->status($status)->create();
    }

    expect(leaderboard())->toBe(['Ann' => 2]);
});

it('BR-7 does not count anonymous ideas and lists no user with zero qualifying ideas', function () {
    $user = User::factory()->create(['name' => 'Ann']);
    Idea::factory()->anonymous()->for($user, 'author')->status(IdeaStatus::Approved)->create();

    expect(leaderboard())->toBe([]);
});

it('F10 excludes withdrawn ideas', function () {
    $user = User::factory()->create(['name' => 'Ann']);
    qualifyingIdea($user);
    qualifyingIdea($user)->delete();

    expect(leaderboard())->toBe(['Ann' => 1]);
});

it('F10 only counts ideas submitted in the current calendar quarter', function () {
    $user = User::factory()->create(['name' => 'Ann']);
    qualifyingIdea($user, ['submitted_at' => '2026-10-01 00:00:00']);
    qualifyingIdea($user, ['submitted_at' => '2026-12-31 23:59:59']);
    qualifyingIdea($user, ['submitted_at' => '2026-09-30 23:59:59']);
    qualifyingIdea($user, ['submitted_at' => '2027-01-01 00:00:00']);
    qualifyingIdea($user, ['submitted_at' => '2025-11-09 12:00:00']);

    expect(leaderboard())->toBe(['Ann' => 2]);
});

it('F10 ranks by count, breaking ties by the oldest qualifying idea then by name', function () {
    $top = User::factory()->create(['name' => 'Zed']);
    $early = User::factory()->create(['name' => 'Yan']);
    $late = User::factory()->create(['name' => 'Bob']);
    $sameDayB = User::factory()->create(['name' => 'Cat']);
    $sameDayA = User::factory()->create(['name' => 'Abe']);

    qualifyingIdea($top, ['submitted_at' => '2026-10-20 09:00:00']);
    qualifyingIdea($top, ['submitted_at' => '2026-10-21 09:00:00']);
    qualifyingIdea($early, ['submitted_at' => '2026-10-02 09:00:00']);
    qualifyingIdea($late, ['submitted_at' => '2026-10-15 09:00:00']);
    qualifyingIdea($sameDayB, ['submitted_at' => '2026-10-25 09:00:00']);
    qualifyingIdea($sameDayA, ['submitted_at' => '2026-10-25 09:00:00']);

    expect(array_keys(leaderboard()))->toBe(['Zed', 'Yan', 'Bob', 'Abe', 'Cat']);
});

it('F10 lists at most ten contributors', function () {
    User::factory()->count(12)->create()->each(fn (User $user) => qualifyingIdea($user));

    expect(leaderboard())->toHaveCount(10);
});

it('F10 uses the quarter of the given date', function () {
    $user = User::factory()->create(['name' => 'Ann']);
    qualifyingIdea($user, ['submitted_at' => '2026-02-10 09:00:00']);

    $rows = app(TopContributors::class)->query(CarbonImmutable::parse('2026-03-31 23:00:00'))->get();

    expect($rows->pluck('name')->all())->toBe(['Ann']);
});

it('F10 renders the leaderboard widget for an employee with rank, name and count', function () {
    $ann = User::factory()->create(['name' => 'Ann Leader']);
    qualifyingIdea($ann);
    qualifyingIdea($ann);
    $this->actingAs($this->employee);

    Livewire::test(LeaderboardWidget::class)
        ->assertSuccessful()
        ->assertSee('Ann Leader')
        ->assertSee('Top contributors this quarter')
        ->assertSeeInOrder(['1', 'Ann Leader', '2']);
});

it('F10 shows the empty state and sits on the /app dashboard', function () {
    $this->actingAs($this->employee)
        ->get('/app')
        ->assertSuccessful()
        ->assertSeeLivewire(LeaderboardWidget::class);

    Livewire::test(LeaderboardWidget::class)->assertSee('No approved ideas yet this quarter');
});
