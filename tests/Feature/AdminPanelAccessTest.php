<?php

use App\Filament\Admin\Resources\Categories\Pages\ManageCategories;
use App\Filament\Admin\Resources\Ideas\Pages\ListIdeas;
use App\Filament\Admin\Resources\Roles\Pages\CreateRole;
use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Filament\Admin\Resources\Roles\Pages\ListRoles;
use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('test 28 redirects guests from /admin to the /app login page', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));

    $this->followingRedirects()->get($url)->assertSuccessful()->assertSee('Sign in');
})->with(['/admin', '/admin/shield/roles', '/admin/categories', '/admin/users', '/admin/ideas']);

it('test 28 serves a single login page, on /app only', function () {
    $this->get('/app/login')->assertSuccessful();
    $this->get('/admin/login')->assertNotFound();
});

it('test 28 forbids employees and reviewers from every /admin URL', function (string $factoryState, string $url) {
    $this->actingAs(User::factory()->{$factoryState}()->create());

    $this->get($url)->assertForbidden();
})->with(['employee', 'reviewer'])->with([
    '/admin',
    '/admin/shield/roles',
    '/admin/categories',
    '/admin/users',
    '/admin/users/create',
    '/admin/ideas',
]);

it('test 28 lets an admin into /admin', function (string $url) {
    $this->actingAs(User::factory()->admin()->create());

    $this->get($url)->assertSuccessful();
})->with(['/admin', '/admin/shield/roles', '/admin/categories', '/admin/users', '/admin/ideas']);

it('keeps Shield\'s role resource on the admin panel only', function () {
    expect(Filament::getPanel('admin')->getResources())->toContain(RoleResource::class)
        ->and(Filament::getPanel('app')->getResources())->not->toContain(RoleResource::class);
});

it('does not let Shield\'s super admin bypass override business rules', function () {
    $admin = User::factory()->admin()->create();
    $own = Idea::factory()->for($admin, 'author')->create();

    expect(Gate::forUser($admin)->denies('changeStatus', $own))->toBeTrue('BR-9')
        ->and(Gate::forUser($admin)->denies('vote', $own))->toBeTrue('BR-2')
        ->and(Gate::forUser($admin)->denies('nonexistent-ability'))->toBeTrue();
});

it('uses the existing admin role as the Shield super admin and creates no second role', function () {
    expect(config('filament-shield.super_admin.name'))->toBe('admin')
        ->and(config('filament-shield.super_admin.define_via_gate'))->toBeFalse()
        ->and(Role::pluck('name')->sort()->values()->all())->toBe(['admin', 'employee', 'reviewer']);
});

it('test 28 forbids direct Livewire calls to the admin pages for employees and reviewers', function (string $role) {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->{$role}()->create());

    foreach ([
        ListRoles::class,
        ManageCategories::class,
        ListUsers::class,
        CreateUser::class,
        ListIdeas::class,
    ] as $page) {
        Livewire::test($page)->assertForbidden();
    }
})->with(['employee', 'reviewer']);

it('forbids employees and reviewers from the role editor pages by Livewire', function (string $role) {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->{$role}()->create());
    $reviewer = Role::findByName('reviewer');

    Livewire::test(EditRole::class, ['record' => $reviewer->getKey()])->assertForbidden();
    Livewire::test(CreateRole::class)->assertForbidden();
    $this->get('/admin/shield/roles/create')->assertForbidden();
    $this->get("/admin/shield/roles/{$reviewer->getKey()}/edit")->assertForbidden();
})->with(['employee', 'reviewer']);

it('shows the role editor to admins in the Administration navigation group', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->admin()->create());

    expect(RoleResource::shouldRegisterNavigation())->toBeTrue()
        ->and(RoleResource::getNavigationGroup())->toBe('Administration');
});
