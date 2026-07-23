<?php

namespace App\Policies;

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
}
