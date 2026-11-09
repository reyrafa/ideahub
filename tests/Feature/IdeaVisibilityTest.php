<?php

use App\Models\Comment;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->author = User::factory()->employee()->create(['name' => 'Alice Author']);
    $this->employee = User::factory()->employee()->create();
    $this->reviewer = User::factory()->reviewer()->create();
    $this->admin = User::factory()->admin()->create();
});

it('BR-7 hides the author of an anonymous idea from employees, reviewers and guests', function (string $viewer) {
    $idea = Idea::factory()->anonymous()->for($this->author, 'author')->create();

    $viewerUser = $viewer === 'guest' ? null : $this->{$viewer};

    expect($idea->authorNameFor($viewerUser))->toBe('Anonymous');
})->with(['employee', 'reviewer', 'guest']);

it('BR-7 reveals the author of an anonymous idea to an admin', function () {
    $idea = Idea::factory()->anonymous()->for($this->author, 'author')->create();

    expect($idea->authorNameFor($this->admin))->toBe('Alice Author')
        ->and($this->admin->can('viewAuthor', $idea))->toBeTrue()
        ->and($this->employee->can('viewAuthor', $idea))->toBeFalse()
        ->and($this->reviewer->can('viewAuthor', $idea))->toBeFalse();
});

it('BR-7 shows the author their own name on their anonymous idea (assumption: the spec only hides identity from others)', function () {
    $idea = Idea::factory()->anonymous()->for($this->author, 'author')->create();

    expect($idea->authorNameFor($this->author))->toBe('Alice Author');
});

it('BR-7 shows the author name on non-anonymous ideas to everyone', function (string $viewer) {
    $idea = Idea::factory()->for($this->author, 'author')->create(['is_anonymous' => false]);

    $viewerUser = $viewer === 'guest' ? null : $this->{$viewer};

    expect($idea->authorNameFor($viewerUser))->toBe('Alice Author');
})->with(['employee', 'reviewer', 'admin', 'guest']);

it('BR-7 applies the same anonymity to comments written by the anonymous idea author', function () {
    $idea = Idea::factory()->anonymous()->for($this->author, 'author')->create();
    $comment = Comment::factory()->for($idea)->for($this->author, 'author')->create();

    expect($comment->authorNameFor($this->employee))->toBe('Anonymous')
        ->and($comment->authorNameFor($this->reviewer))->toBe('Anonymous')
        ->and($comment->authorNameFor(null))->toBe('Anonymous')
        ->and($comment->authorNameFor($this->admin))->toBe('Alice Author')
        ->and($comment->authorNameFor($this->author))->toBe('Alice Author');
});

it('BR-7 shows the real name of other commenters on an anonymous idea', function () {
    $idea = Idea::factory()->anonymous()->for($this->author, 'author')->create();
    $other = User::factory()->employee()->create(['name' => 'Bob Commenter']);
    $comment = Comment::factory()->for($idea)->for($other, 'author')->create();

    expect($comment->authorNameFor($this->employee))->toBe('Bob Commenter')
        ->and($comment->authorNameFor(null))->toBe('Bob Commenter');
});

it('BR-7 shows comment authors on non-anonymous ideas to everyone', function () {
    $idea = Idea::factory()->for($this->author, 'author')->create(['is_anonymous' => false]);
    $comment = Comment::factory()->for($idea)->for($this->author, 'author')->create();

    expect($comment->authorNameFor($this->employee))->toBe('Alice Author');
});
