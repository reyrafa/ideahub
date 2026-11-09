<?php

use App\Actions\HideComment;
use App\Actions\SaveRole;
use App\Filament\Admin\Resources\Roles\Pages\CreateRole;
use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Filament\Admin\Resources\Roles\Pages\ListRoles;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function permissionsOf(string $role): array
{
    return Role::findByName($role)->permissions()->pluck('name')->sort()->values()->all();
}

it('saving a role without touching the checkboxes keeps its permissions', function (string $name) {
    $before = permissionsOf($name);
    $role = Role::findByName($name);

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(permissionsOf($name))->toBe($before)->not->toBeEmpty();
})->with(['employee', 'reviewer', 'admin']);

it('pre-checks the role permissions on the edit form', function () {
    $role = Role::findByName('reviewer');

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->assertFormSet(fn (array $state): bool => in_array('comments.hide', $state['granted']['Comments'], true)
            && ! in_array('users.manage', $state['granted']['Administration'], true));
});

it('removing and re-adding comments.hide changes what a reviewer may do', function () {
    $reviewer = User::factory()->reviewer()->create();
    $comment = Comment::factory()->create();
    $role = Role::findByName('reviewer');

    $grantedWithout = fn (bool $hide): array => [
        'Ideas' => ['ideas.create', 'ideas.vote', 'ideas.change-status'],
        'Comments' => array_values(array_filter(
            ['comments.create', 'comments.view-hidden', $hide ? 'comments.hide' : null],
        )),
        'Administration' => [],
    ];

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->fillForm(['granted' => $grantedWithout(false)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(permissionsOf('reviewer'))->not->toContain('comments.hide')
        ->and($reviewer->fresh()->can('comments.hide'))->toBeFalse();
    expect(fn () => app(HideComment::class)->handle($reviewer->fresh(), $comment))
        ->toThrow(AuthorizationException::class);

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->fillForm(['granted' => $grantedWithout(true)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(permissionsOf('reviewer'))->toContain('comments.hide')
        ->and($reviewer->fresh()->can('comments.hide'))->toBeTrue();
    app(HideComment::class)->handle($reviewer->fresh(), $comment);
    expect($comment->fresh()->is_hidden)->toBeTrue();
});

it('keeps business rules in force whatever permissions a role has', function () {
    $role = Role::findByName('admin');
    expect($role->permissions()->count())->toBe(10);

    $author = User::factory()->admin()->create();
    $idea = Idea::factory()->for($author, 'author')->create();

    expect(Gate::forUser($author)->denies('vote', $idea))->toBeTrue('BR-2')
        ->and(Gate::forUser($author)->denies('changeStatus', $idea))->toBeTrue('BR-9');
});

it('creates a custom role with the checked permissions only', function () {
    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => 'moderator',
            'granted' => ['Ideas' => ['ideas.vote'], 'Comments' => ['comments.hide'], 'Administration' => []],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(permissionsOf('moderator'))->toBe(['comments.hide', 'ideas.vote']);
});

it('rejects duplicate and missing role names', function () {
    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'reviewer'])
        ->call('create')
        ->assertHasFormErrors(['name']);

    Livewire::test(CreateRole::class)
        ->fillForm(['name' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('ignores permissions outside the seeded catalogue', function () {
    $role = app(SaveRole::class)->handle(null, 'sneaky', [['ideas.vote', 'made.up', 'users.manage' => 'x']]);

    expect($role->permissions()->pluck('name')->all())->toBe(['ideas.vote']);
});

it('resets the permission cache after saving', function () {
    $employee = User::factory()->employee()->create();
    expect($employee->can('comments.hide'))->toBeFalse();

    $role = Role::findByName('employee');
    app(SaveRole::class)->handle($role, 'employee', ['ideas.create', 'comments.hide']);

    expect(User::find($employee->id)->can('comments.hide'))->toBeTrue();
});

it('never renames a built-in role', function (string $name) {
    $role = Role::findByName($name);

    expect(fn () => app(SaveRole::class)->handle($role, 'renamed', ['users.manage']))
        ->toThrow(ValidationException::class);

    expect(Role::where('name', $name)->exists())->toBeTrue()
        ->and(Role::where('name', 'renamed')->exists())->toBeFalse();
})->with(['employee', 'reviewer', 'admin']);

it('keeps the built-in name when the name field is not submitted', function () {
    $role = Role::findByName('reviewer');

    app(SaveRole::class)->handle($role, null, ['ideas.vote']);

    expect($role->fresh()->name)->toBe('reviewer')->and(permissionsOf('reviewer'))->toBe(['ideas.vote']);
});

it('disables the name field for built-in roles but not custom ones', function () {
    $custom = Role::create(['name' => 'moderator', 'guard_name' => 'web']);

    Livewire::test(EditRole::class, ['record' => Role::findByName('reviewer')->getKey()])
        ->assertFormFieldIsDisabled('name');
    Livewire::test(EditRole::class, ['record' => $custom->getKey()])
        ->assertFormFieldIsEnabled('name');
});

it('can rename and edit a custom role', function () {
    $custom = Role::create(['name' => 'moderator', 'guard_name' => 'web']);

    Livewire::test(EditRole::class, ['record' => $custom->getKey()])
        ->fillForm(['name' => 'curator', 'granted' => ['Ideas' => ['ideas.vote'], 'Comments' => [], 'Administration' => []]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($custom->fresh()->name)->toBe('curator')
        ->and($custom->fresh()->permissions()->pluck('name')->all())->toBe(['ideas.vote']);
});

it('never lets the admin role lose users.manage', function () {
    $role = Role::findByName('admin');
    $before = permissionsOf('admin');

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->fillForm(['granted' => ['Ideas' => ['ideas.vote'], 'Comments' => [], 'Administration' => ['categories.manage']]])
        ->call('save')
        ->assertHasFormErrors(['granted']);

    expect(permissionsOf('admin'))->toBe($before);

    expect(fn () => app(SaveRole::class)->handle($role, 'admin', []))
        ->toThrow(ValidationException::class);
    expect(permissionsOf('admin'))->toContain('users.manage');
});

it('applies role policy rules for delete', function () {
    $custom = Role::create(['name' => 'moderator', 'guard_name' => 'web']);
    $used = Role::create(['name' => 'used', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($used);

    expect($this->admin->can('delete', $custom))->toBeTrue()
        ->and($this->admin->can('delete', $used))->toBeFalse('has users')
        ->and($this->admin->can('delete', Role::findByName('reviewer')))->toBeFalse('built-in')
        ->and($this->admin->can('update', Role::findByName('reviewer')))->toBeTrue()
        ->and($this->admin->can('create', Role::class))->toBeTrue();
});

it('deletes a custom role from the edit page but refuses built-in or used roles', function () {
    $custom = Role::create(['name' => 'moderator', 'guard_name' => 'web']);
    $used = Role::create(['name' => 'used', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($used);

    Livewire::test(EditRole::class, ['record' => $custom->getKey()])
        ->callAction('delete');
    expect(Role::where('name', 'moderator')->exists())->toBeFalse();

    Livewire::test(EditRole::class, ['record' => Role::findByName('reviewer')->getKey()])
        ->assertActionHidden('delete');
    Livewire::test(EditRole::class, ['record' => $used->getKey()])
        ->assertActionHidden('delete');

    Livewire::test(ListRoles::class)
        ->assertCanSeeTableRecords(Role::all());
});

it('lets only users with users.manage manage roles, even if a role was custom-built', function () {
    $custom = Role::create(['name' => 'helper', 'guard_name' => 'web']);
    $custom->givePermissionTo('ideas.vote');
    $user = User::factory()->create()->assignRole($custom);

    expect($user->can('viewAny', Role::class))->toBeFalse()
        ->and($user->can('create', Role::class))->toBeFalse()
        ->and($user->can('update', $custom))->toBeFalse();
});

it('lists custom roles in the user role select and still protects admins', function () {
    Role::create(['name' => 'content-curator', 'guard_name' => 'web']);
    $target = User::factory()->employee()->create();

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->fillForm(['role' => 'content-curator'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($target->fresh()->hasRole('content-curator'))->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->fillForm(['role' => 'no-such-role'])
        ->call('save')
        ->assertHasFormErrors(['role']);

    Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])
        ->fillForm(['role' => 'content-curator'])
        ->call('save')
        ->assertHasFormErrors(['role']);
    expect($this->admin->fresh()->hasRole('admin'))->toBeTrue();
});
