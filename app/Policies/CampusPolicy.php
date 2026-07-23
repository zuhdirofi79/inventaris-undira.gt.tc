<?php

namespace App\Policies;

use App\Models\Campus;
use App\Models\User;

class CampusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('location.view') || $user->can('location.manage');
    }

    public function view(User $user, Campus $campus): bool
    {
        return $user->can('location.view') || $user->can('location.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('location.manage');
    }

    public function update(User $user, Campus $campus): bool
    {
        return $user->can('location.manage');
    }

    public function delete(User $user, Campus $campus): bool
    {
        return $user->can('location.manage');
    }
}
