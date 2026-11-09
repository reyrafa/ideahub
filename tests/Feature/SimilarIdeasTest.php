<?php

use App\Actions\FindSimilarIdeas;
use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\Pages\CreateIdea;
use App\Filament\Resources\Ideas\Pages\EditIdea;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->employee = User::factory()->employee()->create();
});

function similarTitles(string $title, ?User $viewer, ?int $exclude = null): array
{
    return app(FindSimilarIdeas::class)->handle($title, $viewer, $exclude)->pluck('title')->all();
}

it('F9 matches ideas sharing at least two significant words', function () {
    Idea::factory()->create(['title' => 'Quiet room on the second floor']);

    expect(similarTitles('Add a quiet room for focus work', $this->employee))->toBe(['Quiet room on the second floor']);
});

it('F9 ignores unrelated titles and a single shared word', function () {
    Idea::factory()->create(['title' => 'Free fruit in the kitchen']);
    Idea::factory()->create(['title' => 'Quiet parking for bicycles']);

    expect(similarTitles('Add a quiet room for focus work', $this->employee))->toBe([]);
});

it('F9 matches on a single word when the title has only one significant word', function () {
    Idea::factory()->create(['title' => 'Standing desks for everyone']);

    expect(similarTitles('Desks!', $this->employee))->toBe(['Standing desks for everyone']);
});

it('F9 gives nothing for titles made only of stop words or short words', function (string $title) {
    Idea::factory()->create(['title' => 'This would be what they want with that']);

    expect(similarTitles($title, $this->employee))->toBe([]);
})->with(['stop words' => 'this would what with that', 'short words' => 'a b of it to', 'empty' => '']);

it('F9 ignores punctuation and letter case', function () {
    Idea::factory()->create(['title' => 'Bike-Storage, covered & secure']);

    expect(similarTitles('COVERED bike storage?!', $this->employee))->toHaveCount(1);
});

it('F9 never returns withdrawn ideas', function () {
    Idea::factory()->create(['title' => 'Quiet room on the second floor'])->delete();

    expect(similarTitles('Quiet room please', $this->employee))->toBe([]);
});

it('F9 excludes the idea being edited', function () {
    $idea = Idea::factory()->create(['title' => 'Quiet room on the second floor']);

    expect(similarTitles($idea->title, $this->employee, $idea->id))->toBe([]);
});

it('F9 ranks by shared words, then by votes, and returns at most five', function () {
    Idea::factory()->create(['title' => 'Quiet room', 'votes_count' => 1]);
    Idea::factory()->create(['title' => 'Quiet room focus', 'votes_count' => 0]);
    Idea::factory()->create(['title' => 'Quiet room focus', 'votes_count' => 9]);
    Idea::factory()->count(5)->create(['title' => 'Quiet room', 'votes_count' => 0]);

    $found = app(FindSimilarIdeas::class)->handle('Quiet room focus', $this->employee);

    expect($found)->toHaveCount(5)
        ->and($found->pluck('votes_count')->take(3)->all())->toBe([9, 0, 1])
        ->and($found->first()->title)->toBe('Quiet room focus');
});

it('F9 gives nothing without a viewer', function () {
    Idea::factory()->create(['title' => 'Quiet room on the second floor']);

    expect(similarTitles('Quiet room', null))->toBe([]);
});

it('F9 shows the hint while typing a title on the create form, without the author', function () {
    $author = User::factory()->employee()->create(['name' => 'Secret Author']);
    $idea = Idea::factory()->anonymous()->for($author, 'author')->status(IdeaStatus::UnderReview)
        ->create(['title' => 'Quiet room on the second floor', 'votes_count' => 7]);
    $this->actingAs($this->employee);

    Livewire::test(CreateIdea::class)
        ->assertDontSee('look similar')
        ->fillForm(['title' => 'A quiet room for focus'])
        ->assertSee('look similar')
        ->assertSee('Quiet room on the second floor')
        ->assertSee('7 votes')
        ->assertSee('Under review')
        ->assertSee(route('filament.app.resources.ideas.view', ['record' => $idea]))
        ->assertDontSee('Secret Author')
        ->fillForm(['title' => 'Completely different subject matter'])
        ->assertDontSee('look similar');
});

it('F9 does not list the edited idea in its own hint', function () {
    $idea = Idea::factory()->for($this->employee, 'author')->create(['title' => 'Quiet room on the second floor']);
    $this->actingAs($this->employee);

    Livewire::test(EditIdea::class, ['record' => $idea->getRouteKey()])
        ->assertDontSee('look similar');
});
