<?php

use App\Filament\Admin\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use App\Models\Idea;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->admin()->create());
});

it('lists categories with their active state and ideas count', function () {
    $used = Category::factory()->create(['name' => 'Used category']);
    Idea::factory()->count(2)->create(['category_id' => $used->id]);
    $inactive = Category::factory()->inactive()->create(['name' => 'Dormant category']);

    Livewire::test(ManageCategories::class)
        ->assertCanSeeTableRecords([$used, $inactive])
        ->assertTableColumnStateSet('ideas_count', 2, $used)
        ->assertTableColumnStateSet('is_active', false, $inactive);
});

it('creates a category from the header action', function () {
    Livewire::test(ManageCategories::class)
        ->callAction('create', ['name' => 'Facilities', 'is_active' => true])
        ->assertHasNoActionErrors();

    expect(Category::where('name', 'Facilities')->value('is_active'))->toBeTrue();
});

it('requires a unique name', function () {
    Category::factory()->create(['name' => 'Taken']);

    Livewire::test(ManageCategories::class)
        ->callAction('create', ['name' => 'Taken'])
        ->assertHasActionErrors(['name' => 'unique']);

    Livewire::test(ManageCategories::class)
        ->callAction('create', ['name' => ''])
        ->assertHasActionErrors(['name' => 'required']);
});

it('edits a category', function () {
    $category = Category::factory()->create(['name' => 'Old name']);

    Livewire::test(ManageCategories::class)
        ->callAction(TestAction::make('edit')->table($category), ['name' => 'New name', 'is_active' => false])
        ->assertHasNoActionErrors();

    expect($category->refresh()->name)->toBe('New name')->and($category->is_active)->toBeFalse();
});

it('toggles the active flag inline', function () {
    $category = Category::factory()->create();

    Livewire::test(ManageCategories::class)
        ->call('updateTableColumnState', 'is_active', (string) $category->getKey(), false);

    expect($category->refresh()->is_active)->toBeFalse();
});

it('deletes an unused category but hides Delete for one that has ideas', function () {
    $unused = Category::factory()->create();
    $used = Category::factory()->create();
    Idea::factory()->create(['category_id' => $used->id]);

    Livewire::test(ManageCategories::class)
        ->assertActionVisible(TestAction::make('delete')->table($unused))
        ->assertActionHidden(TestAction::make('delete')->table($used))
        ->callAction(TestAction::make('delete')->table($unused));

    expect(Category::find($unused->id))->toBeNull();
});

it('refuses to delete a category whose only ideas are withdrawn', function () {
    $category = Category::factory()->create();
    Idea::factory()->create(['category_id' => $category->id])->delete();

    Livewire::test(ManageCategories::class)
        ->assertActionHidden(TestAction::make('delete')->table($category));

    expect(User::first()->can('delete', $category))->toBeFalse();
});

it('blocks employees and reviewers from the category page', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    Livewire::test(ManageCategories::class)->assertForbidden();
})->with(['employee', 'reviewer']);
