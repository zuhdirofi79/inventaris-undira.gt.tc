<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\MaintenanceIntervalUnit;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MaintenanceScheduleService
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): MaintenanceSchedule
    {
        $schedule = MaintenanceSchedule::query()->create([
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            $actor,
            AuditAction::Create,
            $schedule,
            null,
            $schedule->only([
                'inventory_item_id', 'maintenance_type', 'interval_value', 'interval_unit',
                'next_maintenance_date', 'remind_before_days', 'responsible_pic', 'is_active',
            ]),
            'MAINTENANCE_SCHEDULE_CREATED',
        );

        return $schedule;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MaintenanceSchedule $schedule, array $data, User $actor): MaintenanceSchedule
    {
        $old = $schedule->only([
            'inventory_item_id', 'maintenance_type', 'interval_value', 'interval_unit',
            'next_maintenance_date', 'remind_before_days', 'responsible_pic', 'is_active', 'notes',
        ]);

        $schedule->update($data);

        $this->audit->log(
            $actor,
            AuditAction::Update,
            $schedule,
            $old,
            $schedule->fresh()->only(array_keys($old)),
            'MAINTENANCE_SCHEDULE_UPDATED',
        );

        return $schedule->fresh();
    }

    public function delete(MaintenanceSchedule $schedule, User $actor): void
    {
        $snapshot = $schedule->only([
            'inventory_item_id', 'maintenance_type', 'next_maintenance_date', 'is_active',
        ]);

        $schedule->delete();

        $this->audit->log($actor, AuditAction::Delete, null, $snapshot, null, 'MAINTENANCE_SCHEDULE_DELETED');
    }

    /**
     * Mark current cycle done and roll next_maintenance_date forward by interval.
     */
    public function completeCycle(MaintenanceSchedule $schedule, User $actor, ?Carbon $completedOn = null): MaintenanceSchedule
    {
        $oldNext = $schedule->next_maintenance_date?->toDateString();
        $from = $completedOn?->copy()->startOfDay() ?? now()->startOfDay();

        // If completing late/early, advance from completion date (not stale next date).
        $schedule->advanceNextDate($from);

        $this->audit->log(
            $actor,
            AuditAction::Update,
            $schedule,
            ['next_maintenance_date' => $oldNext],
            ['next_maintenance_date' => $schedule->next_maintenance_date?->toDateString()],
            'MAINTENANCE_SCHEDULE_ADVANCED',
        );

        return $schedule->fresh();
    }

    /**
     * @return array{due_today: int, due_this_week: int, overdue: int, active: int}
     */
    public function summaryCounts(): array
    {
        return [
            'due_today' => MaintenanceSchedule::query()->dueToday()->count(),
            'due_this_week' => MaintenanceSchedule::query()->dueThisWeek()->count(),
            'overdue' => MaintenanceSchedule::query()->overdue()->count(),
            'active' => MaintenanceSchedule::query()->active()->count(),
        ];
    }

    /**
     * Active schedules whose reminder window has started (including overdue).
     *
     * @return Collection<int, MaintenanceSchedule>
     */
    public function schedulesNeedingReminder(): Collection
    {
        $horizon = now()->addDays(30)->toDateString();

        return MaintenanceSchedule::query()
            ->active()
            ->whereDate('next_maintenance_date', '<=', $horizon)
            ->with(['inventoryItem'])
            ->get()
            ->filter(fn (MaintenanceSchedule $s) => $s->isInReminderWindow())
            ->values();
    }

    public function computeNextDate(Carbon $from, int $value, MaintenanceIntervalUnit|string $unit): Carbon
    {
        $unit = $unit instanceof MaintenanceIntervalUnit
            ? $unit
            : MaintenanceIntervalUnit::from((string) $unit);
        $value = max(1, $value);
        $base = $from->copy()->startOfDay();

        return match ($unit) {
            MaintenanceIntervalUnit::Days => $base->addDays($value),
            MaintenanceIntervalUnit::Weeks => $base->addWeeks($value),
            MaintenanceIntervalUnit::Months => $base->addMonthsNoOverflow($value),
            MaintenanceIntervalUnit::Years => $base->addYearsNoOverflow($value),
        };
    }
}
