<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\User;

class MaintenancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.view');
    }

    public function view(User $user, Maintenance $maintenance): bool
    {
        return $user->can('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance.create');
    }

    public function update(User $user, Maintenance $maintenance): bool
    {
        return $user->can('maintenance.update');
    }

    public function delete(User $user, Maintenance $maintenance): bool
    {
        return $user->can('maintenance.delete');
    }
}
