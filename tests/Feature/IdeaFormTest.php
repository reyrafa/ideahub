<?php

use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Filament\Resources\Ideas\Pages\CreateIdea;
use App\Filament\Resources\Ideas\Pages\EditIdea;
use App\Filament\Resources\Ideas\Pages\ViewIdea;
use App\Models\Category;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->employee = User::factory()->employee()->create();
    $this->admin = User::factory()->admin()->create();
});

it('F1 submits an idea through the form and lands on the idea page', function () {
    $category = Category::factory()->create();
    $this->actingAs($this->employee);

    Livewire::test(CreateIdea::class)
        ->fillForm([
            'title' => 'Add a quiet room on floor two',
            'category_id' => $category->id,
            'problem' => str_repeat('p', 40),
            'proposal' => str_repeat('s', 40),
            'expected_impact' => 'Better focus',
            'is_anonymous' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Idea submitted');

    $idea = Idea::where('title', 'Add a quiet room on floor two')->firstOrFail();

    expect($idea->user_id)->toBe($this->employee->id)
        ->and($idea->status)->toBe(IdeaStatus::New)
        ->and($idea->votes_count)->toBe(0)
        ->and(StatusChange::where('idea_id', $idea->id)->count())->toBe(1);
});

it('BR-5 shows inline validation errors for a short title, short texts and a missing category', function () {
    $this->actingAs($this->employee);

    Livewire::test(CreateIdea::class)
        ->fillForm([
            'title' => 'Short',
            'category_id' => null,
            'problem' => 'too short',
            'proposal' => 'too short',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'title' => 'min',
            'category_id' => 'required',
            'problem' => 'min',
            'proposal' => 'min',
        ]);

    expect(Idea::count())->toBe(0);
});

it('BR-5 rejects a title longer than 120 characters', function () {
    $this->actingAs($this->employee);

    Livewire::test(CreateIdea::class)
        ->fillForm(validIdeaData(['title' => str_repeat('t', 121)]))
        ->call('create')
        ->assertHasFormErrors(['title' => 'max']);
});

it('BR-5 only offers active categories and rejects an inactive one', function () {
    $active = Category::factory()->create(['name' => 'Active category']);
    $inactive = Category::factory()->inactive()->create(['name' => 'Inactive category']);
    $this->actingAs($this->employee);

    Livewire::test(CreateIdea::class)
        ->assertFormFieldExists('category_id', function ($field) use ($active, $inactive): bool {
            $options = $field->getOptions();

            return array_key_exists($active->id, $options) && ! array_key_exists($inactive->id, $options);
        })
        ->fillForm(validIdeaData(['category_id' => $inactive->id]))
        ->call('create')
        ->assertHasFormErrors(['category_id']);

    expect(Idea::count())->toBe(0);
});

it('BR-7 stores the anonymous flag from the checkbox', function () {
    $this->actingAs($this->employee);

    Livewire::test(CreateIdea::class)
        ->fillForm(validIdeaData(['title' => 'An anonymous suggestion here', 'is_anonymous' => true]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Idea::where('title', 'An anonymous suggestion here')->firstOrFail()->is_anonymous)->toBeTrue();
});

it('F1 edits an own New idea with no votes', function () {
    $idea = Idea::factory()->for($this->employee, 'author')->create();
    $this->actingAs($this->employee);

    Livewire::test(EditIdea::class, ['record' => $idea->getKey()])
        ->fillForm(['title' => 'An improved and clearer title'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Idea updated');

    expect($idea->fresh()->title)->toBe('An improved and clearer title');
});

it('BR-6 hides the Edit button after the first vote and blocks a direct edit request', function () {
    $idea = Idea::factory()->for($this->employee, 'author')->create(['votes_count' => 1]);
    Vote::factory()->create(['idea_id' => $idea->id]);
    $this->actingAs($this->employee);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->assertActionHidden('edit');

    $this->get(IdeaResource::getUrl('edit', ['record' => $idea]))->assertForbidden();
});

it('BR-6 blocks editing an idea that is no longer New', function () {
    $idea = Idea::factory()->for($this->employee, 'author')->status(IdeaStatus::UnderReview)->create();
    $this->actingAs($this->employee);

    $this->get(IdeaResource::getUrl('edit', ['record' => $idea]))->assertForbidden();
});

it('BR-6 blocks editing someone else\'s idea', function () {
    $idea = Idea::factory()->create();
    $this->actingAs($this->employee);

    $this->get(IdeaResource::getUrl('edit', ['record' => $idea]))->assertForbidden();
});
