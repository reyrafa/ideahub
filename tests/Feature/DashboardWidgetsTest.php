<?php

use App\Enums\IdeaStatus;
use App\Filament\Widgets\IdeasPerStatus;
use App\Filament\Widgets\NewIdeasThisWeek;
use App\Filament\Widgets\TopIdeas;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->employee = User::factory()->employee()->create();
});

/**
 * Read a protected method of a widget, as the Livewire component would call it.
 */
function widgetData(string $widget): mixed
{
    return (fn () => $this->getData())->call(app($widget));
}

it('F7 shows the three widgets on the /app dashboard', function () {
    $this->actingAs($this->employee)
        ->get('/app')
        ->assertSuccessful()
        ->assertSeeLivewire(NewIdeasThisWeek::class)
        ->assertSeeLivewire(IdeasPerStatus::class)
        ->assertSeeLivewire(TopIdeas::class);
});

it('F7 lists the top 5 ideas by votes with the older idea first on a tie', function () {
    $older = Idea::factory()->create(['votes_count' => 5, 'submitted_at' => now()->subDays(3)]);
    $newer = Idea::factory()->create(['votes_count' => 5, 'submitted_at' => now()->subDay()]);
    $top = Idea::factory()->create(['votes_count' => 9]);
    $third = Idea::factory()->create(['votes_count' => 4]);
    $fourth = Idea::factory()->create(['votes_count' => 3]);
    $sixth = Idea::factory()->create(['votes_count' => 1]);
    $this->actingAs($this->employee);

    Livewire::test(TopIdeas::class)
        ->assertCanSeeTableRecords([$top, $older, $newer, $third, $fourth], inOrder: true)
        ->assertCanNotSeeTableRecords([$sixth]);
});

it('F7 excludes withdrawn ideas from the top ideas', function () {
    $withdrawn = Idea::factory()->create(['votes_count' => 99]);
    $withdrawn->delete();
    $visible = Idea::factory()->create(['votes_count' => 1]);
    $this->actingAs($this->employee);

    Livewire::test(TopIdeas::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$withdrawn]);
});

it('F7 counts ideas per status, zero-filling the empty ones', function () {
    Idea::factory()->count(2)->create();
    Idea::factory()->status(IdeaStatus::Approved)->create();
    Idea::factory()->status(IdeaStatus::Rejected)->create()->delete();

    $data = widgetData(IdeasPerStatus::class);

    expect($data['labels'])->toBe(['New', 'Under review', 'Approved', 'Rejected', 'Implemented'])
        ->and($data['datasets'][0]['data'])->toBe([2, 0, 1, 0, 0]);
});

it('F7 renders the per-status chart for an employee', function () {
    $this->actingAs($this->employee);

    Livewire::test(IdeasPerStatus::class)->assertSuccessful();
});

it('F7 compares new ideas this week with last week and ignores withdrawn ones', function () {
    Idea::factory()->count(3)->create(['submitted_at' => now()->subDays(2)]);
    Idea::factory()->count(1)->create(['submitted_at' => now()->subDays(10)]);
    Idea::factory()->create(['submitted_at' => now()->subDays(20)]);
    Idea::factory()->create(['submitted_at' => now()->subDay()])->delete();
    $this->actingAs($this->employee);

    Livewire::test(NewIdeasThisWeek::class)
        ->assertSee('New ideas this week')
        ->assertSee('+2 vs last week (1)');
});

it('F7 shows a decrease against last week', function () {
    Idea::factory()->count(2)->create(['submitted_at' => now()->subDays(9)]);
    $this->actingAs($this->employee);

    Livewire::test(NewIdeasThisWeek::class)->assertSee('-2 vs last week (2)');
});

it('BR-7 hides the author of an anonymous idea in the top ideas from employees and reviewers', function (string $role) {
    $author = User::factory()->employee()->create(['name' => 'Secret Author']);
    Idea::factory()->anonymous()->for($author, 'author')->create(['votes_count' => 3]);
    $this->actingAs(User::factory()->{$role}()->create());

    Livewire::test(TopIdeas::class)
        ->assertSee('Anonymous')
        ->assertDontSee('Secret Author');
})->with(['employee', 'reviewer']);

it('BR-7 shows the real author of an anonymous idea to an admin and to the author', function () {
    $author = User::factory()->employee()->create(['name' => 'Secret Author']);
    Idea::factory()->anonymous()->for($author, 'author')->create(['votes_count' => 3]);

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(TopIdeas::class)->assertSee('Secret Author');

    $this->actingAs($author);
    Livewire::test(TopIdeas::class)->assertSee('Secret Author');
});
