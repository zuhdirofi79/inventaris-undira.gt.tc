<?php

namespace App\Policies;

use App\Models\MaintenanceSchedule;
use App\Models\User;

class MaintenanceSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.view');
    }

    public function view(User $user, MaintenanceSchedule $schedule): bool
    {
        return $user->can('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance.create');
    }

    public function update(User $user, MaintenanceSchedule $schedule): bool
    {
        return $user->can('maintenance.update');
    }

    public function delete(User $user, MaintenanceSchedule $schedule): bool
    {
        return $user->can('maintenance.delete');
    }
}
