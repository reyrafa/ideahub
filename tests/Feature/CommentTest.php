<?php

use App\Actions\DeleteComment;
use App\Actions\EditComment;
use App\Actions\HideComment;
use App\Actions\PostComment;
use App\Enums\IdeaStatus;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->employee = User::factory()->employee()->create();
    $this->reviewer = User::factory()->reviewer()->create();
    $this->admin = User::factory()->admin()->create();
});

it('BR-12 validates the comment body length (2-1000 characters)', function (int $length, bool $accepted) {
    $idea = Idea::factory()->create();

    $errors = validationErrorsFrom(
        fn () => app(PostComment::class)->handle($this->employee, $idea, str_repeat('c', $length))
    );

    if ($accepted) {
        expect($errors)->toBe([])
            ->and(Comment::where('idea_id', $idea->id)->count())->toBe(1);
    } else {
        expect(array_keys($errors))->toContain('body')
            ->and(Comment::count())->toBe(0);
    }
})->with([
    '1 char rejected' => [1, false],
    '2 chars accepted' => [2, true],
    '1000 chars accepted' => [1000, true],
    '1001 chars rejected' => [1001, false],
]);

it('BR-12 forbids commenting on a Rejected idea', function () {
    $idea = Idea::factory()->status(IdeaStatus::Rejected)->create();

    expect(fn () => app(PostComment::class)->handle($this->employee, $idea, 'Still want to say something'))
        ->toThrow(AuthorizationException::class);

    expect(Comment::count())->toBe(0)
        ->and($this->employee->can('comment', $idea))->toBeFalse();
});

it('BR-12 allows commenting on every other status', function (IdeaStatus $status) {
    $idea = Idea::factory()->status($status)->create();

    $comment = app(PostComment::class)->handle($this->employee, $idea, 'A useful remark');

    expect($comment->idea_id)->toBe($idea->id)
        ->and($comment->user_id)->toBe($this->employee->id)
        ->and($comment->body)->toBe('A useful remark')
        ->and($comment->is_hidden)->toBeFalse();
})->with([
    'new' => [IdeaStatus::New],
    'under review' => [IdeaStatus::UnderReview],
    'approved' => [IdeaStatus::Approved],
    'implemented' => [IdeaStatus::Implemented],
]);

it('BR-13 lets the author edit and delete their comment within the 15 minute window', function () {
    $comment = Comment::factory()->for($this->employee, 'author')->create(['body' => 'Original body']);

    $this->travel(5)->minutes();

    $edited = app(EditComment::class)->handle($this->employee, $comment, 'Edited body');

    expect($edited->body)->toBe('Edited body')
        ->and($comment->fresh()->body)->toBe('Edited body')
        ->and($this->employee->can('update', $comment))->toBeTrue()
        ->and($this->employee->can('delete', $comment))->toBeTrue();

    app(DeleteComment::class)->handle($this->employee, $comment->fresh());

    $this->assertModelMissing($comment);
});

it('BR-13 blocks editing and deleting after 16 minutes', function () {
    $comment = Comment::factory()->for($this->employee, 'author')->create(['body' => 'Original body']);

    $this->travel(16)->minutes();

    expect(fn () => app(EditComment::class)->handle($this->employee, $comment, 'Too late'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteComment::class)->handle($this->employee, $comment))
        ->toThrow(AuthorizationException::class);

    expect($comment->fresh()->body)->toBe('Original body')
        ->and($this->employee->can('update', $comment))->toBeFalse()
        ->and($this->employee->can('delete', $comment))->toBeFalse();
});

it('BR-13 honours the window boundary at 14:59 versus 15:01', function (int $seconds, bool $allowed) {
    $comment = Comment::factory()->for($this->employee, 'author')->create(['body' => 'Original body']);

    $this->travelTo($comment->created_at->copy()->addSeconds($seconds));

    expect($this->employee->can('update', $comment))->toBe($allowed)
        ->and($this->employee->can('delete', $comment))->toBe($allowed);
})->with([
    '14:59 is inside' => [14 * 60 + 59, true],
    '15:01 is outside' => [15 * 60 + 1, false],
]);

it('BR-13 never lets someone else edit or delete a comment, even inside the window', function (string $role) {
    $comment = Comment::factory()->for($this->employee, 'author')->create(['body' => 'Original body']);
    $other = $role === 'employee' ? User::factory()->employee()->create() : $this->{$role};

    expect(fn () => app(EditComment::class)->handle($other, $comment, 'Hijacked'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteComment::class)->handle($other, $comment))
        ->toThrow(AuthorizationException::class);

    expect($comment->fresh()->body)->toBe('Original body');
})->with(['employee', 'reviewer']);

it('BR-14 lets a reviewer hide a comment without deleting it', function () {
    $this->freezeSecond();
    $comment = Comment::factory()->create();

    $hidden = app(HideComment::class)->handle($this->reviewer, $comment);

    expect($hidden->is_hidden)->toBeTrue()
        ->and($comment->fresh()->is_hidden)->toBeTrue()
        ->and($comment->fresh()->hidden_by)->toBe($this->reviewer->id)
        ->and($comment->fresh()->hidden_at->equalTo(now()))->toBeTrue()
        ->and(Comment::whereKey($comment->id)->exists())->toBeTrue();
});

it('BR-14 forbids an employee from hiding a comment', function () {
    $comment = Comment::factory()->create();

    expect(fn () => app(HideComment::class)->handle($this->employee, $comment))
        ->toThrow(AuthorizationException::class);

    expect($comment->fresh()->is_hidden)->toBeFalse()
        ->and($comment->fresh()->hidden_by)->toBeNull()
        ->and($this->employee->can('hide', $comment))->toBeFalse();
});

it('BR-14 shows hidden comments only to users who may view them', function () {
    $idea = Idea::factory()->create();
    $visible = Comment::factory()->for($idea)->create();
    $hidden = Comment::factory()->hidden()->for($idea)->create();

    expect(Comment::visibleTo($this->employee)->pluck('id')->all())->toBe([$visible->id])
        ->and(Comment::visibleTo($this->reviewer)->pluck('id')->sort()->values()->all())->toBe([$visible->id, $hidden->id])
        ->and(Comment::visibleTo($this->admin)->pluck('id')->sort()->values()->all())->toBe([$visible->id, $hidden->id])
        ->and($this->employee->can('view', $hidden))->toBeFalse()
        ->and($this->reviewer->can('view', $hidden))->toBeTrue()
        ->and($this->employee->can('view', $visible))->toBeTrue();
});
