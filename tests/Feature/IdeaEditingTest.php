<?php

use App\Actions\UpdateIdea;
use App\Actions\WithdrawIdea;
use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->author = User::factory()->employee()->create();
    $this->idea = Idea::factory()->for($this->author, 'author')->create(['title' => 'Original idea title']);
});

it('BR-6 lets the author edit a New idea that has no votes', function () {
    $data = validIdeaData(['title' => 'A brand new better title']);

    $updated = app(UpdateIdea::class)->handle($this->author, $this->idea, $data);

    expect($updated->title)->toBe('A brand new better title')
        ->and($this->idea->fresh()->title)->toBe('A brand new better title')
        ->and($this->author->can('update', $this->idea))->toBeTrue();
});

it('BR-6 applies submission validation when editing', function () {
    $data = validIdeaData(['title' => 'short']);

    $errors = validationErrorsFrom(fn () => app(UpdateIdea::class)->handle($this->author, $this->idea, $data));

    expect(array_keys($errors))->toContain('title')
        ->and($this->idea->fresh()->title)->toBe('Original idea title');
});

it('BR-6 blocks the author from editing once the idea has a vote', function () {
    Vote::factory()->create(['idea_id' => $this->idea->id]);
    $this->idea->update(['votes_count' => 1]);
    $this->idea->refresh();

    expect(fn () => app(UpdateIdea::class)->handle($this->author, $this->idea, validIdeaData()))
        ->toThrow(AuthorizationException::class);

    expect($this->idea->fresh()->title)->toBe('Original idea title')
        ->and($this->author->can('update', $this->idea))->toBeFalse();
});

it('BR-6 blocks the author from editing an idea that is no longer New', function (IdeaStatus $status) {
    $this->idea->update(['status' => $status]);

    expect(fn () => app(UpdateIdea::class)->handle($this->author, $this->idea->fresh(), validIdeaData()))
        ->toThrow(AuthorizationException::class);

    expect($this->idea->fresh()->title)->toBe('Original idea title');
})->with([
    'under review' => [IdeaStatus::UnderReview],
    'approved' => [IdeaStatus::Approved],
    'rejected' => [IdeaStatus::Rejected],
    'implemented' => [IdeaStatus::Implemented],
]);

it('BR-6 blocks a non-author from editing', function () {
    $other = User::factory()->employee()->create();

    expect(fn () => app(UpdateIdea::class)->handle($other, $this->idea, validIdeaData()))
        ->toThrow(AuthorizationException::class);

    expect($this->idea->fresh()->title)->toBe('Original idea title');
});

it('BR-6 lets an admin edit any idea regardless of status and votes', function () {
    $admin = User::factory()->admin()->create();
    $idea = Idea::factory()->status(IdeaStatus::Approved)->create(['votes_count' => 1]);
    Vote::factory()->create(['idea_id' => $idea->id]);

    $updated = app(UpdateIdea::class)->handle($admin, $idea, validIdeaData(['title' => 'Edited by an admin']));

    expect($updated->title)->toBe('Edited by an admin')
        ->and($idea->fresh()->title)->toBe('Edited by an admin');
});

it('BR-6 lets the author withdraw a New idea, which soft-deletes it', function () {
    app(WithdrawIdea::class)->handle($this->author, $this->idea);

    expect(Idea::find($this->idea->id))->toBeNull()
        ->and(Idea::withTrashed()->find($this->idea->id))->not->toBeNull();

    $this->assertSoftDeleted($this->idea);
});

it('BR-6 blocks withdrawing an idea that is no longer New', function (IdeaStatus $status) {
    $this->idea->update(['status' => $status]);

    expect(fn () => app(WithdrawIdea::class)->handle($this->author, $this->idea->fresh()))
        ->toThrow(AuthorizationException::class);

    $this->assertNotSoftDeleted($this->idea);
})->with([
    'under review' => [IdeaStatus::UnderReview],
    'approved' => [IdeaStatus::Approved],
    'rejected' => [IdeaStatus::Rejected],
    'implemented' => [IdeaStatus::Implemented],
]);

it('BR-6 blocks anyone but the author from withdrawing', function () {
    $other = User::factory()->employee()->create();

    expect(fn () => app(WithdrawIdea::class)->handle($other, $this->idea))
        ->toThrow(AuthorizationException::class);

    $this->assertNotSoftDeleted($this->idea);
});
