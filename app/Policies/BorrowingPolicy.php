<?php

namespace App\Policies;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use App\Models\User;

class BorrowingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('borrowing.view');
    }

    public function view(User $user, Borrowing $borrowing): bool
    {
        return $user->can('borrowing.view');
    }

    public function create(User $user): bool
    {
        return $user->can('borrowing.create');
    }

    public function update(User $user, Borrowing $borrowing): bool
    {
        return $user->can('borrowing.update');
    }

    public function delete(User $user, Borrowing $borrowing): bool
    {
        return $user->can('borrowing.delete');
    }

    public function approve(User $user, Borrowing $borrowing): bool
    {
        if (! $user->can('borrowing.approve')) {
            return false;
        }

        if ($borrowing->status !== BorrowingStatus::MenungguPersetujuan) {
            return false;
        }

        if ($borrowing->requested_by !== null && (int) $borrowing->requested_by === (int) $user->id) {
            return false;
        }

        return true;
    }

    public function reject(User $user, Borrowing $borrowing): bool
    {
        return $this->approve($user, $borrowing);
    }
}
