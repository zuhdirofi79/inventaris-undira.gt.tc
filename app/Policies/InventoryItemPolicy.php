<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.create');
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('inventory.update');
    }

    public function delete(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('inventory.delete');
    }

    public function restore(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('inventory.restore') || $user->can('trash.restore');
    }

    public function forceDelete(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('trash.force-delete');
    }

    public function import(User $user): bool
    {
        return $user->can('inventory.import');
    }

    public function export(User $user): bool
    {
        return $user->can('inventory.export');
    }
}
