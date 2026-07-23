<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('category.manage') || $user->can('inventory.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('category.manage') || $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('category.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('category.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('category.manage');
    }
}
