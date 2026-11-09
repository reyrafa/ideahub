<?php

use App\Actions\RecordIdeaResults;
use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\Pages\ViewIdea;
use App\Filament\Widgets\TotalSavedWidget;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->employee = User::factory()->employee()->create();
    $this->reviewer = User::factory()->reviewer()->create();
    $this->admin = User::factory()->admin()->create();
});

function implementedIdea(array $attributes = []): Idea
{
    return Idea::factory()->status(IdeaStatus::Implemented)->create($attributes);
}

it('F12 records results on an Implemented idea', function () {
    $idea = implementedIdea();

    app(RecordIdeaResults::class)->handle($this->reviewer, $idea, [
        'hours_saved' => '12.5',
        'money_saved' => '300',
        'results_note' => 'Measured over one quarter.',
    ]);

    $idea->refresh();
    expect($idea->hours_saved)->toBe('12.5')
        ->and($idea->money_saved)->toBe('300.00')
        ->and($idea->results_note)->toBe('Measured over one quarter.')
        ->and($idea->results_recorded_by)->toBe($this->reviewer->id)
        ->and($idea->results_recorded_at)->not->toBeNull();
});

it('F12 accepts only hours or only money', function () {
    $hoursOnly = implementedIdea();
    $moneyOnly = implementedIdea();

    app(RecordIdeaResults::class)->handle($this->reviewer, $hoursOnly, ['hours_saved' => 4]);
    app(RecordIdeaResults::class)->handle($this->reviewer, $moneyOnly, ['money_saved' => 99.5]);

    expect($hoursOnly->fresh()->money_saved)->toBeNull()
        ->and($moneyOnly->fresh()->hours_saved)->toBeNull();
});

it('F12 rejects ideas that are not Implemented', function (IdeaStatus $status) {
    $idea = Idea::factory()->status($status)->create();

    expect(fn () => app(RecordIdeaResults::class)->handle($this->reviewer, $idea, ['hours_saved' => 5]))
        ->toThrow(ValidationException::class);

    expect($idea->fresh()->results_recorded_at)->toBeNull();
})->with(array_filter(IdeaStatus::cases(), fn (IdeaStatus $s): bool => $s !== IdeaStatus::Implemented));

it('F12 forbids an employee from recording results', function () {
    $idea = implementedIdea();

    expect(fn () => app(RecordIdeaResults::class)->handle($this->employee, $idea, ['hours_saved' => 5]))
        ->toThrow(AuthorizationException::class);

    expect($idea->fresh()->hours_saved)->toBeNull();
});

it('F12 forbids a reviewer or an admin from recording results on their own idea', function (string $role) {
    $actor = $this->{$role};
    $idea = implementedIdea(['user_id' => $actor->id]);

    expect(fn () => app(RecordIdeaResults::class)->handle($actor, $idea, ['hours_saved' => 5]))
        ->toThrow(AuthorizationException::class);

    expect($idea->fresh()->hours_saved)->toBeNull();
})->with(['reviewer', 'admin']);

it('F12 lets an admin record results on someone else\'s idea', function () {
    $idea = implementedIdea();

    app(RecordIdeaResults::class)->handle($this->admin, $idea, ['money_saved' => 10]);

    expect($idea->fresh()->money_saved)->toBe('10.00');
});

it('F12 cannot edit the results of a withdrawn idea', function () {
    $idea = implementedIdea();
    $idea->delete();

    expect(fn () => app(RecordIdeaResults::class)->handle($this->reviewer, $idea, ['hours_saved' => 5]))
        ->toThrow(Exception::class);
});

it('F12 rejects invalid figures', function (array $input) {
    $idea = implementedIdea();

    expect(fn () => app(RecordIdeaResults::class)->handle($this->reviewer, $idea, $input))
        ->toThrow(ValidationException::class);

    expect($idea->fresh()->results_recorded_at)->toBeNull();
})->with([
    'negative hours' => [['hours_saved' => -1]],
    'negative money' => [['money_saved' => -0.5]],
    'empty' => [[]],
    'blank strings' => [['hours_saved' => '', 'money_saved' => '  ']],
    'only a note' => [['results_note' => 'Saved a lot']],
    'non numeric hours' => [['hours_saved' => 'abc']],
    'non numeric money' => [['hours_saved' => 1, 'money_saved' => 'lots']],
    'note too long' => [['hours_saved' => 1, 'results_note' => str_repeat('a', 1001)]],
]);

it('F12 replaces earlier figures when re-recording', function () {
    $idea = implementedIdea();
    app(RecordIdeaResults::class)->handle($this->reviewer, $idea, [
        'hours_saved' => 10, 'money_saved' => 500, 'results_note' => 'First',
    ]);

    $this->travel(1)->hours();
    app(RecordIdeaResults::class)->handle($this->admin, $idea, ['hours_saved' => 3]);

    $idea->refresh();
    expect($idea->hours_saved)->toBe('3.0')
        ->and($idea->money_saved)->toBeNull()
        ->and($idea->results_note)->toBeNull()
        ->and($idea->results_recorded_by)->toBe($this->admin->id);
});

it('F12 totals hours and money across Implemented ideas, excluding withdrawn and other statuses', function () {
    implementedIdea(['hours_saved' => 10.5, 'money_saved' => 100]);
    implementedIdea(['hours_saved' => 4]);
    implementedIdea(['money_saved' => 50.25]);
    implementedIdea(['hours_saved' => 1000, 'money_saved' => 1000])->delete();
    Idea::factory()->status(IdeaStatus::Approved)->create(['hours_saved' => 999, 'money_saved' => 999]);
    $this->actingAs($this->employee);

    $stats = (fn () => $this->getStats())->call(app(TotalSavedWidget::class));

    expect($stats[0]->getValue())->toBe('14.5')
        ->and($stats[1]->getValue())->toBe('150.25');
});

it('F12 shows the total widget on the /app dashboard to employees', function () {
    $this->actingAs($this->employee)
        ->get('/app')
        ->assertSuccessful()
        ->assertSeeLivewire(TotalSavedWidget::class);
});

it('F12 shows the Record results action only to allowed users on Implemented ideas', function () {
    $idea = implementedIdea();
    $approved = Idea::factory()->status(IdeaStatus::Approved)->create();
    $own = implementedIdea(['user_id' => $this->reviewer->id]);

    $this->actingAs($this->reviewer);
    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])->assertActionVisible('recordResults');
    Livewire::test(ViewIdea::class, ['record' => $approved->getKey()])->assertActionHidden('recordResults');
    Livewire::test(ViewIdea::class, ['record' => $own->getKey()])->assertActionHidden('recordResults');

    $this->actingAs($this->employee);
    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])->assertActionHidden('recordResults');
});

it('F12 records results through the header action with prefilled values', function () {
    $idea = Idea::factory()->implemented()->create(['hours_saved' => 7, 'money_saved' => null]);
    $this->actingAs($this->reviewer);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->callAction('recordResults', ['hours_saved' => 8, 'money_saved' => 20, 'results_note' => 'Updated'])
        ->assertHasNoActionErrors()
        ->assertNotified('Results recorded');

    expect($idea->fresh()->hours_saved)->toBe('8.0')
        ->and($idea->fresh()->results_recorded_by)->toBe($this->reviewer->id);
});

it('F12 prefills the Record results form with the existing figures', function () {
    $idea = Idea::factory()->implemented()->create(['hours_saved' => 7, 'money_saved' => null]);
    $this->actingAs($this->reviewer);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->mountAction('recordResults')
        ->assertSchemaStateSet(['hours_saved' => '7.0']);
});

it('F12 shows the Results section on the idea page only when results exist', function () {
    $with = Idea::factory()->implemented()->create(['results_note' => 'Fewer tickets every week.']);
    $without = implementedIdea();
    $this->actingAs($this->employee);

    Livewire::test(ViewIdea::class, ['record' => $with->getKey()])
        ->assertSee('Results')
        ->assertSee('Money saved')
        ->assertSee('Fewer tickets every week.');

    Livewire::test(ViewIdea::class, ['record' => $without->getKey()])
        ->assertDontSee('Hours saved');
});

it('F12 does not reveal who recorded the results', function () {
    $recorder = User::factory()->reviewer()->create(['name' => 'Zebediah Recorderson']);
    $idea = Idea::factory()->implemented()->create(['results_recorded_by' => $recorder->id]);
    $this->actingAs($this->employee);

    Livewire::test(ViewIdea::class, ['record' => $idea->getKey()])
        ->assertSee('Money saved')
        ->assertDontSee('Zebediah Recorderson');
});
