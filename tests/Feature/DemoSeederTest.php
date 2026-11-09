<?php

use App\Enums\IdeaStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\DatabaseSeeder;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('seeds the expected number of records', function (): void {
    expect(Category::count())->toBe(5)
        ->and(Category::where('is_active', false)->count())->toBe(1)
        ->and(User::role('employee')->count())->toBe(15)
        ->and(User::role('reviewer')->count())->toBe(2)
        ->and(User::role('admin')->count())->toBe(1)
        ->and(Idea::count())->toBe(20)
        ->and(Idea::where('is_anonymous', true)->count())->toBe(3);
});

it('covers every status', function (): void {
    foreach (IdeaStatus::cases() as $status) {
        expect(Idea::where('status', $status)->exists())->toBeTrue("No idea with status {$status->value}");
    }
});

it('creates the demo logins with the password "password"', function (): void {
    foreach (['employee' => 'employee', 'reviewer' => 'reviewer', 'admin' => 'admin'] as $prefix => $role) {
        $user = User::where('email', "{$prefix}@demo.test")->firstOrFail();

        expect($user->hasRole($role))->toBeTrue()
            ->and(Hash::check('password', $user->password))->toBeTrue();
    }
});

it('keeps votes_count equal to the vote rows for every idea (BR-4)', function (): void {
    Idea::all()->each(
        fn (Idea $idea) => expect($idea->votes_count)->toBe($idea->votes()->count()),
    );

    expect((int) Idea::sum('votes_count'))->toBe(Vote::count());
});

it('never lets an author vote on their own idea (BR-2)', function (): void {
    Idea::with('votes')->get()->each(
        fn (Idea $idea) => expect($idea->votes->pluck('user_id'))->not->toContain($idea->user_id),
    );
});

it('has a clear top five and a tie broken by the older idea (BR-8)', function (): void {
    $ranked = Idea::mostVoted()->limit(6)->get();

    expect($ranked[4]->votes_count)->toBeGreaterThan($ranked[5]->votes_count);

    $tied = Idea::query()->get()->groupBy('votes_count')->filter(fn ($group) => $group->count() > 1 && $group->first()->votes_count >= 10);

    expect($tied)->not->toBeEmpty();

    $pair = $tied->first();

    expect($pair->pluck('submitted_at')->unique())->toHaveCount($pair->count());
});

it('records a consistent status history for every idea (BR-11)', function (): void {
    Idea::with('statusChanges')->get()->each(function (Idea $idea): void {
        $changes = $idea->statusChanges->sortBy('changed_at')->values();

        expect($changes->first()->from_status)->toBeNull()
            ->and($changes->first()->to_status)->toBe(IdeaStatus::New)
            ->and($changes->last()->to_status)->toBe($idea->status);

        $changes->skip(1)->each(function (StatusChange $change): void {
            expect($change->from_status->canTransitionTo($change->to_status))->toBeTrue();
        });
    });
});

it('includes a hidden comment and a comment older than the edit window', function (): void {
    expect(Comment::where('is_hidden', true)->whereNotNull('hidden_by')->count())->toBe(1)
        ->and(Comment::where('created_at', '<', now()->subMinutes(15))->exists())->toBeTrue();
});

it('is safe to run twice', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Idea::count())->toBe(20)->and(User::count())->toBe(18);
});
