<?php

namespace App\Policies;

use App\Models\StockOpname;
use App\Models\User;

class StockOpnamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock-opname.view');
    }

    public function view(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('stock-opname.view');
    }

    public function create(User $user): bool
    {
        return $user->can('stock-opname.create');
    }

    public function update(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('stock-opname.update');
    }

    public function delete(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('stock-opname.delete');
    }
}
