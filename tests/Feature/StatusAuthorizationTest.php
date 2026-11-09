<?php

use App\Actions\ChangeIdeaStatus;
use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('BR-9 forbids an employee from changing any idea status', function () {
    $employee = User::factory()->employee()->create();
    $idea = Idea::factory()->create();

    expect(fn () => app(ChangeIdeaStatus::class)->handle($employee, $idea, IdeaStatus::UnderReview))
        ->toThrow(AuthorizationException::class);

    expect($idea->fresh()->status)->toBe(IdeaStatus::New)
        ->and(StatusChange::count())->toBe(0)
        ->and($employee->can('changeStatus', $idea))->toBeFalse();
});

it('BR-9 forbids a user without any role from changing a status', function () {
    $roleless = User::factory()->create();
    $idea = Idea::factory()->create();

    expect(fn () => app(ChangeIdeaStatus::class)->handle($roleless, $idea, IdeaStatus::UnderReview))
        ->toThrow(AuthorizationException::class);

    expect($idea->fresh()->status)->toBe(IdeaStatus::New);
});

it('BR-9 forbids a reviewer from changing the status of their own idea', function () {
    $reviewer = User::factory()->reviewer()->create();
    $idea = Idea::factory()->for($reviewer, 'author')->create();

    expect(fn () => app(ChangeIdeaStatus::class)->handle($reviewer, $idea, IdeaStatus::UnderReview))
        ->toThrow(AuthorizationException::class);

    expect($idea->fresh()->status)->toBe(IdeaStatus::New)
        ->and(StatusChange::count())->toBe(0)
        ->and($reviewer->can('changeStatus', $idea))->toBeFalse();
});

it('BR-9 forbids an admin from changing the status of their own idea', function () {
    $admin = User::factory()->admin()->create();
    $idea = Idea::factory()->for($admin, 'author')->create();

    expect(fn () => app(ChangeIdeaStatus::class)->handle($admin, $idea, IdeaStatus::UnderReview))
        ->toThrow(AuthorizationException::class);

    expect($idea->fresh()->status)->toBe(IdeaStatus::New)
        ->and(StatusChange::count())->toBe(0)
        ->and($admin->can('changeStatus', $idea))->toBeFalse();
});

it('BR-9 lets reviewers and admins change the status of someone else\'s idea', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $idea = Idea::factory()->create();

    $change = app(ChangeIdeaStatus::class)->handle($actor, $idea, IdeaStatus::UnderReview);

    expect($actor->can('changeStatus', $idea))->toBeTrue()
        ->and($change->changed_by)->toBe($actor->id)
        ->and($idea->fresh()->status)->toBe(IdeaStatus::UnderReview);
})->with(['reviewer', 'admin']);
