<?php

use App\Enums\IdeaStatus;
use App\Filament\Pages\MyIdeas;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->employee = User::factory()->employee()->create();
});

it('F6 lists only the signed-in user\'s ideas with status and votes', function () {
    $mine = Idea::factory()->for($this->employee, 'author')->create(['title' => 'My own good idea', 'votes_count' => 7]);
    $other = Idea::factory()->create(['title' => 'Somebody else\'s idea']);
    $this->actingAs($this->employee);

    Livewire::test(MyIdeas::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other])
        ->assertSee('My own good idea')
        ->assertSee('7')
        ->assertSee('New');
});

it('F6 includes the user\'s anonymous ideas tagged as private', function () {
    $anonymous = Idea::factory()->anonymous()->for($this->employee, 'author')->create(['title' => 'My secret suggestion']);
    $this->actingAs($this->employee);

    Livewire::test(MyIdeas::class)
        ->assertCanSeeTableRecords([$anonymous])
        ->assertSee('Private (anonymous)');
});

it('F6 shows the empty state and a Submit idea action when there are no ideas', function () {
    $this->actingAs($this->employee);

    Livewire::test(MyIdeas::class)
        ->assertSee("You haven't submitted any ideas yet")
        ->assertActionVisible('submit');
});

it('BR-6 offers Edit and Withdraw only while the idea is New and unvoted', function () {
    $editable = Idea::factory()->for($this->employee, 'author')->create();
    $voted = Idea::factory()->for($this->employee, 'author')->create(['votes_count' => 1]);
    $inReview = Idea::factory()->for($this->employee, 'author')->status(IdeaStatus::UnderReview)->create();
    $this->actingAs($this->employee);

    Livewire::test(MyIdeas::class)
        ->assertTableActionVisible('edit', $editable)
        ->assertTableActionVisible('withdraw', $editable)
        ->assertTableActionHidden('edit', $voted)
        ->assertTableActionVisible('withdraw', $voted)
        ->assertTableActionHidden('edit', $inReview)
        ->assertTableActionHidden('withdraw', $inReview);
});

it('BR-6 withdraws an idea from the list after confirmation', function () {
    $idea = Idea::factory()->for($this->employee, 'author')->create();
    $this->actingAs($this->employee);

    Livewire::test(MyIdeas::class)
        ->callTableAction('withdraw', $idea)
        ->assertNotified('Idea withdrawn')
        ->assertCanNotSeeTableRecords([$idea]);

    expect(Idea::find($idea->id))->toBeNull();
});

it('requires a role to open My ideas', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/app/my-ideas')->assertForbidden();
});

it('lets a user with a role open My ideas by URL', function () {
    $this->actingAs($this->employee);

    $this->get('/app/my-ideas')->assertSuccessful();
});
