<?php

use App\Actions\ChangeIdeaStatus;
use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->reviewer = User::factory()->reviewer()->create();
});

it('BR-9 and BR-11 walks an idea New -> Under review -> Approved -> Implemented, recording every change', function () {
    $this->freezeSecond();
    $idea = Idea::factory()->create();
    $steps = [
        [IdeaStatus::UnderReview, IdeaStatus::New, null],
        [IdeaStatus::Approved, IdeaStatus::UnderReview, 'Looks promising, approved.'],
        [IdeaStatus::Implemented, IdeaStatus::Approved, 'Shipped in the October release.'],
    ];

    foreach ($steps as [$to, $from, $note]) {
        $change = app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea->fresh(), $to, $note);

        expect($change)->toBeInstanceOf(StatusChange::class)
            ->and($change->idea_id)->toBe($idea->id)
            ->and($change->from_status)->toBe($from)
            ->and($change->to_status)->toBe($to)
            ->and($change->note)->toBe($note)
            ->and($change->changed_by)->toBe($this->reviewer->id)
            ->and($change->changed_at->equalTo(now()))->toBeTrue()
            ->and($idea->fresh()->status)->toBe($to);
    }

    expect(StatusChange::where('idea_id', $idea->id)->count())->toBe(3);
});

it('BR-9 and BR-10 rejects an idea under review when a note is given', function () {
    $idea = Idea::factory()->status(IdeaStatus::UnderReview)->create();
    $note = 'This duplicates an existing initiative.';

    $change = app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea, IdeaStatus::Rejected, $note);

    expect($change->note)->toBe($note)
        ->and($idea->fresh()->status)->toBe(IdeaStatus::Rejected);
});

it('BR-9 refuses every transition the workflow does not allow and changes nothing', function (IdeaStatus $from, IdeaStatus $to) {
    $idea = Idea::factory()->status($from)->create();
    StatusChange::factory()->create(['idea_id' => $idea->id]);

    $errors = validationErrorsFrom(
        fn () => app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea, $to, str_repeat('n', 30))
    );

    expect(array_keys($errors))->toContain('status')
        ->and($idea->fresh()->status)->toBe($from)
        ->and(StatusChange::where('idea_id', $idea->id)->count())->toBe(1);
})->with([
    'new to implemented' => [IdeaStatus::New, IdeaStatus::Implemented],
    'new to approved' => [IdeaStatus::New, IdeaStatus::Approved],
    'new to rejected' => [IdeaStatus::New, IdeaStatus::Rejected],
    'new to new' => [IdeaStatus::New, IdeaStatus::New],
    'under review to new' => [IdeaStatus::UnderReview, IdeaStatus::New],
    'under review to implemented' => [IdeaStatus::UnderReview, IdeaStatus::Implemented],
    'approved to rejected' => [IdeaStatus::Approved, IdeaStatus::Rejected],
    'approved to under review' => [IdeaStatus::Approved, IdeaStatus::UnderReview],
    'rejected to approved' => [IdeaStatus::Rejected, IdeaStatus::Approved],
    'rejected to under review' => [IdeaStatus::Rejected, IdeaStatus::UnderReview],
    'implemented to new' => [IdeaStatus::Implemented, IdeaStatus::New],
    'implemented to approved' => [IdeaStatus::Implemented, IdeaStatus::Approved],
]);

it('BR-10 requires a note of at least 20 characters for Rejected and Implemented', function (IdeaStatus $from, IdeaStatus $to, ?string $note, bool $accepted) {
    $idea = Idea::factory()->status($from)->create();

    $errors = validationErrorsFrom(
        fn () => app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea, $to, $note)
    );

    if ($accepted) {
        expect($errors)->toBe([])
            ->and($idea->fresh()->status)->toBe($to);
    } else {
        expect(array_keys($errors))->toContain('note')
            ->and($idea->fresh()->status)->toBe($from)
            ->and(StatusChange::where('idea_id', $idea->id)->count())->toBe(0);
    }
})->with([
    'reject without note' => [IdeaStatus::UnderReview, IdeaStatus::Rejected, null, false],
    'reject with empty note' => [IdeaStatus::UnderReview, IdeaStatus::Rejected, '', false],
    'reject with 19 chars' => [IdeaStatus::UnderReview, IdeaStatus::Rejected, str_repeat('n', 19), false],
    'reject with 20 chars' => [IdeaStatus::UnderReview, IdeaStatus::Rejected, str_repeat('n', 20), true],
    'implement without note' => [IdeaStatus::Approved, IdeaStatus::Implemented, null, false],
    'implement with empty note' => [IdeaStatus::Approved, IdeaStatus::Implemented, '', false],
    'implement with 19 chars' => [IdeaStatus::Approved, IdeaStatus::Implemented, str_repeat('n', 19), false],
    'implement with 20 chars' => [IdeaStatus::Approved, IdeaStatus::Implemented, str_repeat('n', 20), true],
]);

it('BR-10 does not require a note for the other transitions', function (IdeaStatus $from, IdeaStatus $to) {
    $idea = Idea::factory()->status($from)->create();

    $change = app(ChangeIdeaStatus::class)->handle($this->reviewer, $idea, $to);

    expect($change->note)->toBeNull()
        ->and($idea->fresh()->status)->toBe($to);
})->with([
    'new to under review' => [IdeaStatus::New, IdeaStatus::UnderReview],
    'under review to approved' => [IdeaStatus::UnderReview, IdeaStatus::Approved],
]);
