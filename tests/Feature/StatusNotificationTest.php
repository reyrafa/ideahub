<?php

use App\Actions\ChangeIdeaStatus;
use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\User;
use App\Notifications\IdeaStatusChanged;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->reviewer = User::factory()->reviewer()->create();
    $this->author = User::factory()->employee()->create(['name' => 'Alice Author']);
    Notification::fake();
});

it('F8 notifies the author on every valid change of the workflow', function () {
    $idea = Idea::factory()->for($this->author, 'author')->create();
    $steps = [
        [IdeaStatus::UnderReview, null],
        [IdeaStatus::Approved, null],
        [IdeaStatus::Implemented, 'Shipped in the October release.'],
    ];

    foreach ($steps as [$to, $note]) {
        app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea->fresh(), $to, $note);
    }

    Notification::assertSentToTimes($this->author, IdeaStatusChanged::class, 3);
    Notification::assertSentTo($this->author, IdeaStatusChanged::class, fn (IdeaStatusChanged $n): bool => $n->from === IdeaStatus::Approved
        && $n->to === IdeaStatus::Implemented
        && $n->note === 'Shipped in the October release.');
    Notification::assertNothingSentTo($this->reviewer);
});

it('F8 notifies the author when an idea is rejected with a note', function () {
    $idea = Idea::factory()->for($this->author, 'author')->status(IdeaStatus::UnderReview)->create();

    app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea, IdeaStatus::Rejected, 'This duplicates an existing initiative.');

    Notification::assertSentTo($this->author, IdeaStatusChanged::class, fn (IdeaStatusChanged $n): bool => $n->to === IdeaStatus::Rejected
        && $n->note === 'This duplicates an existing initiative.');
});

it('F8 sends nothing for an invalid transition', function () {
    $idea = Idea::factory()->for($this->author, 'author')->create();

    validationErrorsFrom(fn () => app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea, IdeaStatus::Implemented, str_repeat('n', 30)));

    Notification::assertNothingSent();
});

it('F8 sends nothing when the required note is missing', function () {
    $idea = Idea::factory()->for($this->author, 'author')->status(IdeaStatus::UnderReview)->create();

    validationErrorsFrom(fn () => app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea, IdeaStatus::Rejected));

    Notification::assertNothingSent();
});

it('F8 sends nothing when an employee tries to change a status', function () {
    $idea = Idea::factory()->for($this->author, 'author')->create();
    $employee = User::factory()->employee()->create();

    expect(fn () => app(ChangeIdeaStatus::class)->handle($employee, $idea, IdeaStatus::UnderReview))
        ->toThrow(AuthorizationException::class);

    Notification::assertNothingSent();
});

it('F8 sends nothing when a reviewer changes the status of their own idea', function () {
    $idea = Idea::factory()->for($this->reviewer, 'author')->create();

    expect(fn () => app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea, IdeaStatus::UnderReview))
        ->toThrow(AuthorizationException::class);

    Notification::assertNothingSent();
});

it('F8 is queued and sent after the transaction commits', function () {
    $notification = new IdeaStatusChanged(Idea::factory()->create(), IdeaStatus::New, IdeaStatus::UnderReview);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue();
});

it('F8 mail shows the title, both statuses, the note and a link to the idea', function () {
    $idea = Idea::factory()->for($this->author, 'author')->create(['title' => 'Add a quiet room']);
    $mail = (new IdeaStatusChanged($idea, IdeaStatus::UnderReview, IdeaStatus::Rejected, 'Not feasible this year, sorry.'))
        ->toMail($this->author);

    $text = implode("\n", [$mail->subject, $mail->greeting, ...$mail->introLines, $mail->actionText, $mail->actionUrl]);

    expect($text)->toContain('Add a quiet room')
        ->toContain('Under review')
        ->toContain('Rejected')
        ->toContain('Not feasible this year, sorry.')
        ->toContain('Hello Alice Author,')
        ->and($mail->actionUrl)->toContain((string) $idea->id);
});

it('BR-7 mail never names the author of an anonymous idea nor the reviewer', function () {
    $idea = Idea::factory()->anonymous()->for($this->author, 'author')->create();
    $mail = (new IdeaStatusChanged($idea, IdeaStatus::New, IdeaStatus::UnderReview))->toMail($this->author);

    $text = implode("\n", [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines]);

    expect($mail->greeting)->toBe('Hello,')
        ->and($text)->not->toContain('Alice Author')
        ->and($text)->not->toContain($this->reviewer->name);
});
