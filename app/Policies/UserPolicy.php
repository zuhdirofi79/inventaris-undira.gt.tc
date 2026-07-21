<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('user.manage');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('user.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('user.manage');
    }

    /**
     * Profile updates. Role/status changes are gated in changeRole / changeStatus
     * and enforced again in the controller.
     */
    public function update(User $actor, User $target): bool
    {
        if (! $actor->can('user.manage')) {
            return false;
        }

        // Only the Primary Super Admin may edit the Primary account (including self).
        if ($target->isPrimarySuperAdmin()) {
            return $actor->isPrimarySuperAdmin();
        }

        // Non-primary Super Admins cannot manage other Super Admins.
        if ($target->isSuperAdmin() && ! $actor->isPrimarySuperAdmin()) {
            return false;
        }

        return true;
    }

    public function delete(User $actor, User $target): bool
    {
        if (! $actor->can('user.manage')) {
            return false;
        }

        if ($target->isPrimarySuperAdmin()) {
            return false;
        }

        if ($actor->id === $target->id) {
            return false;
        }

        if ($target->isSuperAdmin()) {
            return $actor->isPrimarySuperAdmin();
        }

        return true;
    }

    public function changeRole(User $actor, User $target): bool
    {
        if (! $actor->can('user.manage')) {
            return false;
        }

        if ($target->isPrimarySuperAdmin()) {
            return false;
        }

        if ($actor->id === $target->id && $actor->isPrimarySuperAdmin()) {
            return false;
        }

        if ($target->isSuperAdmin()) {
            return $actor->isPrimarySuperAdmin();
        }

        return true;
    }

    public function changeStatus(User $actor, User $target): bool
    {
        if (! $actor->can('user.manage')) {
            return false;
        }

        if ($target->isPrimarySuperAdmin()) {
            return false;
        }

        if ($actor->id === $target->id && $actor->isPrimarySuperAdmin()) {
            return false;
        }

        if ($target->isSuperAdmin()) {
            return $actor->isPrimarySuperAdmin();
        }

        return true;
    }

    public function manageSuperAdmin(User $actor): bool
    {
        return $actor->isPrimarySuperAdmin();
    }
}
