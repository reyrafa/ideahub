<?php

use App\Actions\ToggleVote;
use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('BR-1 toggles a vote on and off, moving the count 4 -> 5 -> 4', function () {
    $idea = Idea::factory()->create(['votes_count' => 4]);
    Vote::factory()->count(4)->create(['idea_id' => $idea->id]);
    $voter = User::factory()->employee()->create();

    expect(app(ToggleVote::class)->handle($voter, $idea))->toBeTrue()
        ->and($idea->fresh()->votes_count)->toBe(5);

    expect(app(ToggleVote::class)->handle($voter, $idea->fresh()))->toBeFalse()
        ->and($idea->fresh()->votes_count)->toBe(4);
});

it('BR-1 never holds more than one vote row per user and idea however often it is toggled', function () {
    $idea = Idea::factory()->create();
    $voter = User::factory()->employee()->create();

    foreach (range(1, 6) as $attempt) {
        app(ToggleVote::class)->handle($voter, $idea->fresh());

        expect(Vote::where('idea_id', $idea->id)->where('user_id', $voter->id)->count())
            ->toBe($attempt % 2);
    }
});

it('BR-1 enforces one vote per user and idea with a database unique index', function () {
    $vote = Vote::factory()->create();

    Vote::factory()->create(['idea_id' => $vote->idea_id, 'user_id' => $vote->user_id]);
})->throws(UniqueConstraintViolationException::class);

it('BR-2 forbids the author from voting on their own idea', function (bool $anonymous) {
    $author = User::factory()->employee()->create();
    $idea = Idea::factory()->for($author, 'author')->create(['is_anonymous' => $anonymous]);

    expect(fn () => app(ToggleVote::class)->handle($author, $idea))
        ->toThrow(AuthorizationException::class);

    expect(Vote::count())->toBe(0)
        ->and($idea->fresh()->votes_count)->toBe(0)
        ->and($author->can('vote', $idea))->toBeFalse();
})->with([
    'public idea' => [false],
    'anonymous idea' => [true],
]);

it('BR-3 cannot add a vote once the idea is no longer open for voting', function (IdeaStatus $status) {
    $idea = Idea::factory()->status($status)->create();
    $voter = User::factory()->employee()->create();

    expect(fn () => app(ToggleVote::class)->handle($voter, $idea))
        ->toThrow(AuthorizationException::class);

    expect(Vote::count())->toBe(0)
        ->and($idea->fresh()->votes_count)->toBe(0);
})->with([
    'approved' => [IdeaStatus::Approved],
    'rejected' => [IdeaStatus::Rejected],
    'implemented' => [IdeaStatus::Implemented],
]);

it('BR-3 cannot remove a vote once the idea is no longer open for voting', function (IdeaStatus $status) {
    $voter = User::factory()->employee()->create();
    $idea = Idea::factory()->status($status)->create(['votes_count' => 1]);
    Vote::factory()->create(['idea_id' => $idea->id, 'user_id' => $voter->id]);

    expect(fn () => app(ToggleVote::class)->handle($voter, $idea))
        ->toThrow(AuthorizationException::class);

    expect(Vote::count())->toBe(1)
        ->and($idea->fresh()->votes_count)->toBe(1);
})->with([
    'approved' => [IdeaStatus::Approved],
    'rejected' => [IdeaStatus::Rejected],
    'implemented' => [IdeaStatus::Implemented],
]);

it('BR-3 still allows voting while the idea is New or Under review', function (IdeaStatus $status) {
    $idea = Idea::factory()->status($status)->create();
    $voter = User::factory()->employee()->create();

    expect(app(ToggleVote::class)->handle($voter, $idea))->toBeTrue()
        ->and($idea->fresh()->votes_count)->toBe(1)
        ->and(app(ToggleVote::class)->handle($voter, $idea->fresh()))->toBeFalse()
        ->and($idea->fresh()->votes_count)->toBe(0);
})->with([
    'new' => [IdeaStatus::New],
    'under review' => [IdeaStatus::UnderReview],
]);

it('BR-4 keeps votes_count equal to the vote rows after a mixed add/remove sequence by several users', function () {
    $idea = Idea::factory()->create();
    [$a, $b, $c, $d] = User::factory()->employee()->count(4)->create()->all();

    $sequence = [$a, $b, $c, $a, $d, $b, $b, $c, $a, $a];

    foreach ($sequence as $user) {
        app(ToggleVote::class)->handle($user, $idea->fresh());

        expect($idea->fresh()->votes_count)->toBe(Vote::where('idea_id', $idea->id)->count());
    }

    expect(Vote::where('idea_id', $idea->id)->count())->toBe(2);
});

it('BR-4 produces a correct stored count even when called with a stale idea instance', function () {
    $idea = Idea::factory()->create();
    $stale = Idea::findOrFail($idea->id);
    [$first, $second, $third] = User::factory()->employee()->count(3)->create()->all();

    app(ToggleVote::class)->handle($first, Idea::findOrFail($idea->id));
    app(ToggleVote::class)->handle($second, Idea::findOrFail($idea->id));

    expect($stale->votes_count)->toBe(0);

    app(ToggleVote::class)->handle($third, $stale);

    expect($idea->fresh()->votes_count)->toBe(3)
        ->and(Vote::where('idea_id', $idea->id)->count())->toBe(3);

    app(ToggleVote::class)->handle($third, $stale);

    expect($idea->fresh()->votes_count)->toBe(2)
        ->and(Vote::where('idea_id', $idea->id)->count())->toBe(2);
});
