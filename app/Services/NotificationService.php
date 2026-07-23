<?php

namespace App\Services;

use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use App\Models\DeletionApproval;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\StockOpname;
use App\Models\User;
use App\Notifications\BorrowingCreatedNotification;
use App\Notifications\DeletionApprovalNotification;
use App\Notifications\MaintenanceDueNotification;
use App\Notifications\MaintenanceScheduleReminderNotification;
use App\Notifications\OverdueBorrowingNotification;
use App\Notifications\StockOpnameStatusNotification;
use App\Notifications\WarrantyExpiringNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Roles that receive operational alerts.
     *
     * @var list<string>
     */
    private const OPERATOR_ROLES = ['super-admin', 'admin', 'staff'];

    public function notifyWarrantyExpiring(User $user, InventoryItem $item, int $daysRemaining): bool
    {
        if ($this->alreadyNotified($user, WarrantyExpiringNotification::class, 'inventory_item_id', $item->id)) {
            return false;
        }

        $user->notify(new WarrantyExpiringNotification($item, $daysRemaining));

        return true;
    }

    public function notifyMaintenanceDue(User $user, Maintenance $maintenance): bool
    {
        if ($this->alreadyNotified($user, MaintenanceDueNotification::class, 'maintenance_id', $maintenance->id)) {
            return false;
        }

        $user->notify(new MaintenanceDueNotification($maintenance));

        return true;
    }

    public function notifyMaintenanceScheduleReminder(User $user, MaintenanceSchedule $schedule): bool
    {
        if ($this->alreadyNotified(
            $user,
            MaintenanceScheduleReminderNotification::class,
            'maintenance_schedule_id',
            $schedule->id,
            withinDays: 1,
        )) {
            return false;
        }

        $user->notify(new MaintenanceScheduleReminderNotification($schedule));

        return true;
    }

    public function notifyOverdueBorrowing(User $user, Borrowing $borrowing): bool
    {
        if ($this->alreadyNotified($user, OverdueBorrowingNotification::class, 'borrowing_id', $borrowing->id)) {
            return false;
        }

        $user->notify(new OverdueBorrowingNotification($borrowing));

        return true;
    }

    public function notifyBorrowingCreated(Borrowing $borrowing): int
    {
        $borrowing->loadMissing('inventoryItem');
        $count = 0;

        foreach ($this->operatorUsers() as $user) {
            if ($this->alreadyNotified($user, BorrowingCreatedNotification::class, 'borrowing_id', $borrowing->id)) {
                continue;
            }

            $user->notify(new BorrowingCreatedNotification($borrowing));
            $count++;
        }

        return $count;
    }

    public function notifyDeletionApproval(DeletionApproval $approval, string $event = 'requested'): int
    {
        $approval->loadMissing(['inventoryItem', 'requestedBy']);
        $count = 0;

        $recipients = match ($event) {
            'requested' => $this->usersWithPermission('deletion-approval.approve'),
            default => collect([
                $approval->requestedBy,
                ...$this->usersWithPermission('deletion-approval.approve')->all(),
            ])->filter()->unique('id')->values(),
        };

        foreach ($recipients as $user) {
            if (! $user instanceof User || ! $user->is_active) {
                continue;
            }

            $exists = $user->notifications()
                ->where('type', DeletionApprovalNotification::class)
                ->where('created_at', '>=', now()->subDay())
                ->where('data->deletion_approval_id', $approval->id)
                ->where('data->event', $event)
                ->exists();

            if ($exists) {
                continue;
            }

            $user->notify(new DeletionApprovalNotification($approval, $event));
            $count++;
        }

        return $count;
    }

    public function notifyStockOpnameStatus(StockOpname $stockOpname, string $event): int
    {
        $count = 0;

        foreach ($this->operatorUsers() as $user) {
            $exists = $user->notifications()
                ->where('type', StockOpnameStatusNotification::class)
                ->where('created_at', '>=', now()->subDay())
                ->where('data->stock_opname_id', $stockOpname->id)
                ->where('data->event', $event)
                ->exists();

            if ($exists) {
                continue;
            }

            $user->notify(new StockOpnameStatusNotification($stockOpname, $event));
            $count++;
        }

        return $count;
    }

    public function dispatchWarrantyExpiringAlerts(int $days = 30): int
    {
        $items = InventoryItem::query()
            ->warrantyExpiringSoon($days)
            ->with(['room.floor.building.campus'])
            ->get();

        $users = $this->operatorUsers();
        $count = 0;

        foreach ($items as $item) {
            $daysRemaining = (int) now()->diffInDays($item->warranty_expiry, false);

            foreach ($users as $user) {
                if ($this->notifyWarrantyExpiring($user, $item, max(0, $daysRemaining))) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function dispatchMaintenanceDueAlerts(): int
    {
        $maintenances = Maintenance::query()
            ->whereNotNull('next_schedule')
            ->whereDate('next_schedule', '<=', now()->addDays(7))
            ->whereDate('next_schedule', '>=', now()->subDay())
            ->with(['inventoryItem', 'vendor'])
            ->get();

        $users = $this->operatorUsers();
        $count = 0;

        foreach ($maintenances as $maintenance) {
            foreach ($users as $user) {
                if ($this->notifyMaintenanceDue($user, $maintenance)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Preventive schedules: due within remind_before_days window or overdue.
     */
    public function dispatchMaintenanceScheduleAlerts(): int
    {
        $schedules = app(MaintenanceScheduleService::class)->schedulesNeedingReminder();
        $users = $this->operatorUsers();
        $count = 0;

        foreach ($schedules as $schedule) {
            foreach ($users as $user) {
                if ($this->notifyMaintenanceScheduleReminder($user, $schedule)) {
                    $count++;
                }
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

        $users = $this->operatorUsers();
        $count = 0;

        foreach ($borrowings as $borrowing) {
            $borrowing->update(['status' => BorrowingStatus::Terlambat]);

            foreach ($users as $user) {
                if ($this->notifyOverdueBorrowing($user, $borrowing)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @return array{warranty: int, maintenance: int, schedules: int, overdue: int, total: int}
     */
    public function dispatchAllScheduledAlerts(): array
    {
        $warranty = $this->dispatchWarrantyExpiringAlerts();
        $maintenance = $this->dispatchMaintenanceDueAlerts();
        $schedules = $this->dispatchMaintenanceScheduleAlerts();
        $overdue = $this->dispatchOverdueBorrowingAlerts();

        return [
            'warranty' => $warranty,
            'maintenance' => $maintenance,
            'schedules' => $schedules,
            'overdue' => $overdue,
            'total' => $warranty + $maintenance + $schedules + $overdue,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    protected function operatorUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::OPERATOR_ROLES))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    protected function usersWithPermission(string $permission): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->permission($permission)
            ->get();
    }

    protected function alreadyNotified(
        User $user,
        string $notificationClass,
        string $dataKey,
        int|string $dataValue,
        int $withinDays = 1,
    ): bool {
        return $user->notifications()
            ->where('type', $notificationClass)
            ->where('created_at', '>=', now()->subDays($withinDays))
            ->where("data->{$dataKey}", $dataValue)
            ->exists();
    }
}
