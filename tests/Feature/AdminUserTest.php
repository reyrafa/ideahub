<?php

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists users with email, department and role, and filters by role and department', function () {
    $employee = User::factory()->employee()->create(['department' => 'Sales']);
    $reviewer = User::factory()->reviewer()->create(['department' => 'Support']);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$employee, $reviewer, $this->admin])
        ->assertTableColumnStateSet('roles.name', ['reviewer'], $reviewer)
        ->filterTable('role', 'reviewer')
        ->assertCanSeeTableRecords([$reviewer])
        ->assertCanNotSeeTableRecords([$employee, $this->admin])
        ->removeTableFilter('role')
        ->filterTable('department', 'Sales')
        ->assertCanSeeTableRecords([$employee])
        ->assertCanNotSeeTableRecords([$reviewer]);
});

it('searches users by name and email', function () {
    $ada = User::factory()->employee()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
    $bob = User::factory()->employee()->create(['name' => 'Bob Builder', 'email' => 'bob@example.test']);

    Livewire::test(ListUsers::class)
        ->searchTable('Lovelace')
        ->assertCanSeeTableRecords([$ada])
        ->assertCanNotSeeTableRecords([$bob])
        ->searchTable('bob@example')
        ->assertCanSeeTableRecords([$bob])
        ->assertCanNotSeeTableRecords([$ada]);
});

it('creates a user with a role and a hashed password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New Person',
            'email' => 'new@example.test',
            'department' => 'Finance',
            'role' => 'reviewer',
            'password' => 'a-secret-pass',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'new@example.test')->firstOrFail();

    expect($user->hasRole('reviewer'))->toBeTrue()
        ->and($user->roles)->toHaveCount(1)
        ->and($user->department)->toBe('Finance')
        ->and(Hash::check('a-secret-pass', $user->password))->toBeTrue();
});

it('validates required fields, a unique email and a known role on create', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '', 'email' => 'taken@example.test', 'password' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique', 'password' => 'required']);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'X', 'email' => 'x@example.test', 'password' => 'secret-pass', 'role' => 'superuser'])
        ->call('create')
        ->assertHasFormErrors(['role']);
});

it('edits a user and changes their role without touching the password', function () {
    $user = User::factory()->employee()->create();
    $originalHash = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->assertFormSet(['role' => 'employee'])
        ->assertFormFieldHidden('password')
        ->fillForm(['name' => 'Renamed', 'department' => 'HR', 'role' => 'reviewer'])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Renamed')
        ->and($user->department)->toBe('HR')
        ->and($user->hasRole('reviewer'))->toBeTrue()
        ->and($user->hasRole('employee'))->toBeFalse()
        ->and($user->password)->toBe($originalHash);
});

it('can promote someone else to admin and demote another admin', function () {
    $user = User::factory()->employee()->create();
    $otherAdmin = User::factory()->admin()->create();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['role' => 'admin'])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(EditUser::class, ['record' => $otherAdmin->getKey()])
        ->fillForm(['role' => 'employee'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->hasRole('admin'))->toBeTrue()
        ->and($otherAdmin->refresh()->hasRole('admin'))->toBeFalse()
        ->and($otherAdmin->hasRole('employee'))->toBeTrue();
});

it('refuses to let an admin remove their own admin role, server side', function () {
    Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])
        ->fillForm(['role' => 'employee', 'name' => 'Changed Name'])
        ->call('save')
        ->assertHasFormErrors(['role']);

    expect($this->admin->refresh()->hasRole('admin'))->toBeTrue()
        ->and($this->admin->name)->not->toBe('Changed Name');
});

it('lets an admin edit their own details while staying admin', function () {
    Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])
        ->fillForm(['department' => 'Operations'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->admin->refresh()->department)->toBe('Operations')
        ->and($this->admin->hasRole('admin'))->toBeTrue();
});

it('blocks employees and reviewers from the user pages', function (string $role) {
    $this->actingAs($user = User::factory()->{$role}()->create());

    Livewire::test(ListUsers::class)->assertForbidden();
    Livewire::test(CreateUser::class)->assertForbidden();
    Livewire::test(EditUser::class, ['record' => $user->getKey()])->assertForbidden();
})->with(['employee', 'reviewer']);

it('never lets an admin delete their own account', function () {
    $other = User::factory()->employee()->create();

    expect($this->admin->can('delete', $this->admin))->toBeFalse()
        ->and($this->admin->can('delete', $other))->toBeTrue();
});

it('rejects a weak or overlong password on create', function () {
    foreach (['short', str_repeat('a', 256)] as $password) {
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Pw Test', 'email' => 'pw@example.test', 'role' => 'employee', 'password' => $password])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    expect(User::where('email', 'pw@example.test')->exists())->toBeFalse();
});

it('refuses to demote the last remaining admin', function () {
    $manager = User::factory()->employee()->create();
    $manager->givePermissionTo('users.manage');
    $this->actingAs($manager);

    Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])
        ->fillForm(['role' => 'reviewer'])
        ->call('save')
        ->assertHasFormErrors(['role']);

    expect($this->admin->refresh()->hasRole('admin'))->toBeTrue();
});

it('rolls back the user update when the role change is refused', function () {
    Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])
        ->fillForm(['name' => 'Half Saved', 'role' => 'employee'])
        ->call('save');

    expect($this->admin->refresh()->name)->not->toBe('Half Saved');
});
