<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('categories.manage');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('categories.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('categories.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('categories.manage');
    }

    /**
     * Categories in use (withdrawn ideas included) are deactivated instead of deleted.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories.manage') && ! $category->ideas()->withTrashed()->exists();
    }
}
