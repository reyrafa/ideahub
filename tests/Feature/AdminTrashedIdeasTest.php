<?php

use App\Filament\Admin\Resources\Ideas\Pages\ListIdeas;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
});

it('lists withdrawn ideas by default and hides live ones', function () {
    $withdrawn = Idea::factory()->create();
    $withdrawn->delete();
    $live = Idea::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(ListIdeas::class)
        ->assertCanSeeTableRecords([$withdrawn])
        ->assertCanNotSeeTableRecords([$live]);
});

it('lets the trashed filter show live and all ideas', function () {
    $withdrawn = Idea::factory()->create();
    $withdrawn->delete();
    $live = Idea::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(ListIdeas::class)
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$withdrawn, $live])
        ->removeTableFilter('trashed')
        ->assertCanSeeTableRecords([$live])
        ->assertCanNotSeeTableRecords([$withdrawn]);
});

it('restores a withdrawn idea', function () {
    $idea = Idea::factory()->create();
    $idea->delete();
    $this->actingAs($this->admin);

    Livewire::test(ListIdeas::class)
        ->callAction(TestAction::make('restore')->table($idea))
        ->assertNotified('Idea restored');

    expect($idea->fresh()->trashed())->toBeFalse();
});

it('shows the real author beside Anonymous to an admin', function () {
    $author = User::factory()->employee()->create(['name' => 'Hidden Hannah']);
    $idea = Idea::factory()->anonymous()->for($author, 'author')->create();
    $idea->delete();
    $this->actingAs($this->admin);

    Livewire::test(ListIdeas::class)->assertSee('Hidden Hannah (anonymous)');
});

it('denies restore to employees and reviewers, at the policy and the page', function (string $role) {
    $idea = Idea::factory()->create();
    $idea->delete();
    $user = User::factory()->{$role}()->create();
    $this->actingAs($user);

    expect($user->can('restore', $idea))->toBeFalse()
        ->and($user->can('forceDelete', $idea))->toBeFalse();

    Livewire::test(ListIdeas::class)->assertForbidden();

    expect($idea->fresh()->trashed())->toBeTrue();
})->with(['employee', 'reviewer']);

it('never lets anyone force delete an idea, admins included', function () {
    $idea = Idea::factory()->create();

    expect($this->admin->can('restore', $idea))->toBeTrue()
        ->and($this->admin->can('forceDelete', $idea))->toBeFalse();
});
