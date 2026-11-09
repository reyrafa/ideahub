<?php

use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\Pages\ListIdeas;
use App\Models\Category;
use App\Models\Idea;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->employee = User::factory()->employee()->create();
    $this->reviewer = User::factory()->reviewer()->create();
    $this->admin = User::factory()->admin()->create();
});

it('renders the board with titles, categories, votes and statuses', function () {
    $category = Category::factory()->create(['name' => 'Safety']);
    $idea = Idea::factory()->for($category)->create(['title' => 'Install better door signs', 'votes_count' => 3]);

    $this->actingAs($this->employee);

    Livewire::test(ListIdeas::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$idea])
        ->assertSee('Install better door signs')
        ->assertSee('Safety')
        ->assertSee('New');
});

it('requires a role to open the board', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/app/ideas')->assertForbidden();
});

it('BR-8 sorts by most votes then oldest first by default', function () {
    $older = Idea::factory()->create(['title' => 'Older idea with five votes', 'votes_count' => 5, 'submitted_at' => now()->subDays(3)]);
    $newer = Idea::factory()->create(['title' => 'Newer idea with five votes', 'votes_count' => 5, 'submitted_at' => now()->subDay()]);
    $top = Idea::factory()->create(['title' => 'Top idea with nine votes', 'votes_count' => 9, 'submitted_at' => now()]);

    $this->actingAs($this->employee);

    Livewire::test(ListIdeas::class)
        ->assertCanSeeTableRecords([$top, $older, $newer], inOrder: true);
});

it('sorts by newest on request', function () {
    Idea::factory()->create(['title' => 'Oldest but most voted', 'votes_count' => 9, 'submitted_at' => now()->subDays(3)]);
    Idea::factory()->create(['title' => 'Newest with no votes', 'votes_count' => 0, 'submitted_at' => now()]);

    $this->actingAs($this->employee);

    Livewire::test(ListIdeas::class)
        ->filterTable('sort', 'newest')
        ->assertSeeInOrder(['Newest with no votes', 'Oldest but most voted']);
});

it('filters by category and status', function () {
    $safety = Category::factory()->create();
    $inSafety = Idea::factory()->for($safety)->create();
    $other = Idea::factory()->create();
    $approved = Idea::factory()->status(IdeaStatus::Approved)->create();

    $this->actingAs($this->employee);

    Livewire::test(ListIdeas::class)
        ->filterTable('category', $safety->id)
        ->assertCanSeeTableRecords([$inSafety])
        ->assertCanNotSeeTableRecords([$other, $approved])
        ->resetTableFilters()
        ->filterTable('status', IdeaStatus::Approved->value)
        ->assertCanSeeTableRecords([$approved])
        ->assertCanNotSeeTableRecords([$other, $inSafety]);
});

it('shows the empty state when nothing matches', function () {
    $this->actingAs($this->employee);

    Livewire::test(ListIdeas::class)
        ->assertSee('No ideas match your filters');
});

it('BR-7 shows Anonymous to employees and reviewers and the real name to admins on the board', function () {
    $author = User::factory()->employee()->create(['name' => 'Alice Author']);
    $idea = Idea::factory()->anonymous()->for($author, 'author')->create();

    foreach ([$this->employee, $this->reviewer] as $viewer) {
        $this->actingAs($viewer);

        Livewire::test(ListIdeas::class)
            ->assertCanSeeTableRecords([$idea])
            ->assertSee('Anonymous')
            ->assertDontSee('Alice Author');
    }

    $this->actingAs($this->admin);

    Livewire::test(ListIdeas::class)->assertSee('Alice Author');
});

it('BR-1 votes and removes a vote from the board, updating the count in place', function () {
    $idea = Idea::factory()->create(['votes_count' => 4]);
    Vote::factory()->count(4)->create(['idea_id' => $idea->id]);

    $this->actingAs($this->employee);

    $component = Livewire::test(ListIdeas::class)
        ->callTableAction('vote', $idea);

    expect($idea->fresh()->votes_count)->toBe(5);

    $component->callTableAction('vote', $idea);

    expect($idea->fresh()->votes_count)->toBe(4)
        ->and(Vote::where('idea_id', $idea->id)->count())->toBe(4);
});

it('BR-2 disables voting on your own idea', function () {
    $idea = Idea::factory()->for($this->employee, 'author')->create();

    $this->actingAs($this->employee);

    Livewire::test(ListIdeas::class)
        ->assertTableActionDisabled('vote', $idea);

    expect($idea->fresh()->votes_count)->toBe(0);
});

it('BR-3 disables voting once an idea is no longer open', function (IdeaStatus $status) {
    $idea = Idea::factory()->status($status)->create(['votes_count' => 2]);

    $this->actingAs($this->employee);

    Livewire::test(ListIdeas::class)
        ->assertTableActionDisabled('vote', $idea);
})->with([IdeaStatus::Approved, IdeaStatus::Rejected, IdeaStatus::Implemented]);

it('shows the Submit idea header action only to users who may create', function () {
    $this->actingAs($this->employee);

    Livewire::test(ListIdeas::class)->assertActionVisible('create');
});
