<?php

use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\Pages\ViewIdea;
use App\Livewire\IdeaStatusHistory;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->employee = User::factory()->employee()->create();
    $this->reviewer = User::factory()->reviewer()->create();
    $this->admin = User::factory()->admin()->create();
});

it('F5 moves an idea New to Under review to Approved to Implemented and records every step', function () {
    $idea = Idea::factory()->create();
    $this->actingAs($this->reviewer);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->callAction('changeStatus', ['status' => IdeaStatus::UnderReview->value])
        ->assertHasNoActionErrors()
        ->assertNotified('Status changed to Under review')
        ->callAction('changeStatus', ['status' => IdeaStatus::Approved->value])
        ->assertHasNoActionErrors()
        ->callAction('changeStatus', [
            'status' => IdeaStatus::Implemented->value,
            'note' => 'Signs installed on all floors last week.',
        ])
        ->assertHasNoActionErrors()
        ->assertActionHidden('changeStatus');

    expect($idea->fresh()->status)->toBe(IdeaStatus::Implemented);

    $changes = StatusChange::where('idea_id', $idea->id)->orderBy('id')->get();

    expect($changes)->toHaveCount(3)
        ->and($changes->pluck('to_status')->all())->toBe([IdeaStatus::UnderReview, IdeaStatus::Approved, IdeaStatus::Implemented])
        ->and($changes->pluck('changed_by')->unique()->all())->toBe([$this->reviewer->id])
        ->and($changes->last()->note)->toBe('Signs installed on all floors last week.');

    Livewire::test(IdeaStatusHistory::class, ['idea' => $idea])
        ->assertSee($this->reviewer->name)
        ->assertSee('Signs installed on all floors last week.');
});

it('BR-11 shows the recorded history on the idea page right after a status change', function () {
    $idea = Idea::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->callAction('changeStatus', ['status' => IdeaStatus::UnderReview->value])
        ->assertDispatched('idea-updated');

    Livewire::test(IdeaStatusHistory::class, ['idea' => $idea])
        ->assertSee('Under review')
        ->assertSee($this->admin->name);
});

it('BR-10 requires a note of at least 20 characters to reject', function () {
    $idea = Idea::factory()->status(IdeaStatus::UnderReview)->create();
    $this->actingAs($this->reviewer);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->callAction('changeStatus', ['status' => IdeaStatus::Rejected->value, 'note' => ''])
        ->assertHasActionErrors(['note' => 'required'])
        ->unmountAction()
        ->callAction('changeStatus', ['status' => IdeaStatus::Rejected->value, 'note' => 'Too short'])
        ->assertHasActionErrors(['note' => 'min'])
        ->unmountAction()
        ->callAction('changeStatus', ['status' => IdeaStatus::Rejected->value, 'note' => 'Not feasible within this year\'s budget.'])
        ->assertHasNoActionErrors();

    expect($idea->fresh()->status)->toBe(IdeaStatus::Rejected);
});

it('BR-10 requires a note to mark an idea implemented but not to approve it', function () {
    $approved = Idea::factory()->status(IdeaStatus::Approved)->create();
    $underReview = Idea::factory()->status(IdeaStatus::UnderReview)->create();
    $this->actingAs($this->reviewer);

    Livewire::test(ViewIdea::class, ['record' => $approved->getKey()])
        ->callAction('changeStatus', ['status' => IdeaStatus::Implemented->value])
        ->assertHasActionErrors(['note' => 'required']);

    expect($approved->fresh()->status)->toBe(IdeaStatus::Approved);

    Livewire::test(ViewIdea::class, ['record' => $underReview->getKey()])
        ->callAction('changeStatus', ['status' => IdeaStatus::Approved->value])
        ->assertHasNoActionErrors();

    expect($underReview->fresh()->status)->toBe(IdeaStatus::Approved);
});

it('only offers allowed transitions: New cannot jump to Implemented', function () {
    $idea = Idea::factory()->create();
    $this->actingAs($this->reviewer);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->callAction('changeStatus', ['status' => IdeaStatus::Implemented->value, 'note' => str_repeat('n', 30)])
        ->assertHasActionErrors(['status']);

    expect($idea->fresh()->status)->toBe(IdeaStatus::New)
        ->and(StatusChange::where('idea_id', $idea->id)->count())->toBe(0);
});

it('hides Change status on ideas without further transitions', function (IdeaStatus $status) {
    $idea = Idea::factory()->status($status)->create();
    $this->actingAs($this->reviewer);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->assertActionHidden('changeStatus');
})->with([IdeaStatus::Rejected, IdeaStatus::Implemented]);

it('BR-9 gives employees no Change status action and blocks a forced call', function () {
    $idea = Idea::factory()->create();
    $this->actingAs($this->employee);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->assertActionHidden('changeStatus')
        ->call('mountAction', 'changeStatus')
        ->set('mountedActions.0.data.status', IdeaStatus::UnderReview->value)
        ->call('callMountedAction');

    expect($idea->fresh()->status)->toBe(IdeaStatus::New)
        ->and(StatusChange::where('idea_id', $idea->id)->count())->toBe(0);
});

it('BR-9 stops a reviewer and an admin from changing the status of their own idea', function () {
    foreach ([$this->reviewer, $this->admin] as $user) {
        $idea = Idea::factory()->for($user, 'author')->create();
        $this->actingAs($user);

        Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
            ->assertActionHidden('changeStatus')
            ->call('mountAction', 'changeStatus')
            ->set('mountedActions.0.data.status', IdeaStatus::UnderReview->value)
            ->call('callMountedAction');

        expect($idea->fresh()->status)->toBe(IdeaStatus::New);
    }
});

it('shows Change status to a reviewer on someone else\'s idea', function () {
    $idea = Idea::factory()->create();
    $this->actingAs($this->reviewer);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->assertActionVisible('changeStatus');
});
