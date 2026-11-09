<?php

use App\Enums\IdeaStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\StatusChange;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('builds valid rows with every factory and state', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(User::factory()->employee()->create()->hasRole('employee'))->toBeTrue()
        ->and(User::factory()->reviewer()->create()->hasRole('reviewer'))->toBeTrue()
        ->and(User::factory()->admin()->create()->hasRole('admin'))->toBeTrue()
        ->and(Category::factory()->inactive()->create()->is_active)->toBeFalse()
        ->and(Idea::factory()->anonymous()->create()->is_anonymous)->toBeTrue()
        ->and(Comment::factory()->hidden()->create()->is_hidden)->toBeTrue()
        ->and(Vote::factory()->create())->toBeInstanceOf(Vote::class)
        ->and(StatusChange::factory()->create())->toBeInstanceOf(StatusChange::class);

    foreach (IdeaStatus::cases() as $status) {
        expect(Idea::factory()->status($status)->create()->fresh()->status)->toBe($status);
    }
});

it('rejects a duplicate vote for the same idea and user', function () {
    $vote = Vote::factory()->create();

    Vote::factory()->create(['idea_id' => $vote->idea_id, 'user_id' => $vote->user_id]);
})->throws(UniqueConstraintViolationException::class);

it('orders ideas by votes then oldest first', function () {
    $older = Idea::factory()->create(['votes_count' => 3, 'submitted_at' => now()->subDay()]);
    $newer = Idea::factory()->create(['votes_count' => 3, 'submitted_at' => now()]);
    $top = Idea::factory()->create(['votes_count' => 5]);

    expect(Idea::mostVoted()->pluck('id')->all())->toBe([$top->id, $older->id, $newer->id]);
});

it('allows only the documented status transitions', function (IdeaStatus $from, IdeaStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'new to under review' => [IdeaStatus::New, IdeaStatus::UnderReview, true],
    'under review to approved' => [IdeaStatus::UnderReview, IdeaStatus::Approved, true],
    'under review to rejected' => [IdeaStatus::UnderReview, IdeaStatus::Rejected, true],
    'approved to implemented' => [IdeaStatus::Approved, IdeaStatus::Implemented, true],
    'new to implemented' => [IdeaStatus::New, IdeaStatus::Implemented, false],
    'new to approved' => [IdeaStatus::New, IdeaStatus::Approved, false],
    'under review to new' => [IdeaStatus::UnderReview, IdeaStatus::New, false],
    'approved to rejected' => [IdeaStatus::Approved, IdeaStatus::Rejected, false],
    'rejected to approved' => [IdeaStatus::Rejected, IdeaStatus::Approved, false],
    'implemented to new' => [IdeaStatus::Implemented, IdeaStatus::New, false],
]);

it('exposes status rules for notes, voting and comments', function () {
    expect(IdeaStatus::Rejected->requiresNote())->toBeTrue()
        ->and(IdeaStatus::Implemented->requiresNote())->toBeTrue()
        ->and(IdeaStatus::Approved->requiresNote())->toBeFalse()
        ->and(IdeaStatus::New->isVotingOpen())->toBeTrue()
        ->and(IdeaStatus::UnderReview->isVotingOpen())->toBeTrue()
        ->and(IdeaStatus::Approved->isVotingOpen())->toBeFalse()
        ->and(IdeaStatus::Rejected->allowsComments())->toBeFalse()
        ->and(IdeaStatus::Implemented->allowsComments())->toBeTrue();
});

it('seeds the roles and permissions idempotently', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::pluck('name')->sort()->values()->all())->toBe(['admin', 'employee', 'reviewer'])
        ->and(Permission::count())->toBe(10)
        ->and(Role::findByName('employee')->permissions)->toHaveCount(3)
        ->and(Role::findByName('reviewer')->permissions)->toHaveCount(6)
        ->and(Role::findByName('admin')->permissions)->toHaveCount(10);
});

it('limits the admin panel to admins and the app panel to users with a role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $employee = User::factory()->employee()->create();
    $admin = User::factory()->admin()->create();
    $roleless = User::factory()->create();

    $this->actingAs($employee)->get('/admin')->assertForbidden();
    $this->actingAs($employee)->get('/app')->assertSuccessful();
    $this->actingAs($admin)->get('/admin')->assertSuccessful();
    $this->actingAs($roleless)->get('/app')->assertForbidden();
});
