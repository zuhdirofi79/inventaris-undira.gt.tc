<?php

namespace App\Services;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\User;
use App\Notifications\MaintenanceDueNotification;
use App\Notifications\OverdueBorrowingNotification;
use App\Notifications\WarrantyExpiringNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    public function notifyWarrantyExpiring(User $user, InventoryItem $item, int $daysRemaining): void
    {
        $user->notify(new WarrantyExpiringNotification($item, $daysRemaining));
    }

    public function notifyMaintenanceDue(User $user, Maintenance $maintenance): void
    {
        $user->notify(new MaintenanceDueNotification($maintenance));
    }

    public function notifyOverdueBorrowing(User $user, Borrowing $borrowing): void
    {
        $user->notify(new OverdueBorrowingNotification($borrowing));
    }

    public function dispatchWarrantyExpiringAlerts(int $days = 30): int
    {
        $items = InventoryItem::query()
            ->warrantyExpiringSoon($days)
            ->with(['room.floor.building.campus'])
            ->get();

        $users = $this->adminUsers();
        $count = 0;

        foreach ($items as $item) {
            $daysRemaining = (int) now()->diffInDays($item->warranty_expiry, false);

            foreach ($users as $user) {
                $this->notifyWarrantyExpiring($user, $item, max(0, $daysRemaining));
                $count++;
            }
        }

        return $count;
    }

    public function dispatchMaintenanceDueAlerts(): int
    {
        $maintenances = Maintenance::query()
            ->whereNotNull('next_schedule')
            ->whereDate('next_schedule', '<=', now()->addDays(7))
            ->with(['inventoryItem', 'vendor'])
            ->get();

        $users = $this->adminUsers();
        $count = 0;

        foreach ($maintenances as $maintenance) {
            foreach ($users as $user) {
                $this->notifyMaintenanceDue($user, $maintenance);
                $count++;
            }
        }

        return $count;
    }

    public function dispatchOverdueBorrowingAlerts(): int
    {
        $borrowings = Borrowing::query()
            ->where('status', BorrowingStatus::Dipinjam)
            ->whereDate('expected_return_date', '<', now())
            ->with('inventoryItem')
            ->get();

        $users = $this->adminUsers();
        $count = 0;

        foreach ($borrowings as $borrowing) {
            $borrowing->update(['status' => BorrowingStatus::Terlambat]);

            foreach ($users as $user) {
                $this->notifyOverdueBorrowing($user, $borrowing);
                $count++;
            }
        }

        return $count;
    }

    protected function adminUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin']))
            ->get();
    }
}
