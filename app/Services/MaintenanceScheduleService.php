<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class MaintenanceScheduleService
{
    public function __construct(
        protected AuditLogService $audit,
        protected InventorySearchService $inventorySearch,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): MaintenanceSchedule
    {
        $type = (string) ($data['maintenance_type'] ?? '');
        $itemId = (int) ($data['inventory_item_id'] ?? 0);

        if ($dup = $this->findDuplicateActive($itemId, $type)) {
            throw new RuntimeException(
                'Jadwal aktif untuk jenis ini sudah ada (jadwal #'.$dup->id.'). Nonaktifkan atau edit jadwal tersebut.'
            );
        }

        $data = $this->normalizeSchedulePayload($data);

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

        $itemId = (int) ($data['inventory_item_id'] ?? $schedule->inventory_item_id);
        $type = (string) ($data['maintenance_type'] ?? $schedule->maintenance_type?->value ?? '');
        $willBeActive = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : (bool) $schedule->is_active;

        if ($willBeActive && ($dup = $this->findDuplicateActive($itemId, $type, $schedule->id))) {
            throw new RuntimeException(
                'Jadwal aktif untuk jenis ini sudah ada (jadwal #'.$dup->id.').'
            );
        }

        if (
            isset($data['interval_value'], $data['interval_unit'])
            && empty($data['next_maintenance_date'])
        ) {
            $data['next_maintenance_date'] = $this->suggestNextDate(
                $itemId,
                (int) $data['interval_value'],
                $data['interval_unit']
            )->toDateString();
        }

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
     * Active duplicate: same inventory + same maintenance type.
     */
    public function findDuplicateActive(int $inventoryItemId, string $maintenanceType, ?int $exceptId = null): ?MaintenanceSchedule
    {
        if ($inventoryItemId < 1 || $maintenanceType === '') {
            return null;
        }

        $query = MaintenanceSchedule::query()
            ->active()
            ->where('inventory_item_id', $inventoryItemId)
            ->where('maintenance_type', $maintenanceType);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->first();
    }

    /**
     * Preview after inventory selected (AJAX / create page).
     *
     * @return array<string, mixed>
     */
    public function previewForInventory(int $inventoryItemId, ?string $maintenanceType = null): array
    {
        $ctx = $this->inventorySearch->maintenanceContext($inventoryItemId) ?? [];
        $type = $maintenanceType ?: MaintenanceType::Preventive->value;

        $lastMaintenance = $this->lastMaintenanceSummary($inventoryItemId);
        $existing = $this->findDuplicateActive($inventoryItemId, $type);
        $existingAny = MaintenanceSchedule::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->orderByDesc('is_active')
            ->orderBy('next_maintenance_date')
            ->get(['id', 'maintenance_type', 'interval_value', 'interval_unit', 'next_maintenance_date', 'remind_before_days', 'responsible_pic', 'is_active']);

        $suggestedIntervalValue = 3;
        $suggestedIntervalUnit = MaintenanceIntervalUnit::Months->value;
        $suggestedRemind = 7;

        if ($existing) {
            $suggestedIntervalValue = (int) $existing->interval_value;
            $suggestedIntervalUnit = $existing->interval_unit?->value ?? $suggestedIntervalUnit;
            $suggestedRemind = (int) $existing->remind_before_days;
        } elseif ($existingAny->isNotEmpty()) {
            $ref = $existingAny->first(fn (MaintenanceSchedule $s) => $s->is_active) ?? $existingAny->first();
            if ($ref) {
                $suggestedIntervalValue = (int) $ref->interval_value;
                $suggestedIntervalUnit = $ref->interval_unit?->value ?? $suggestedIntervalUnit;
                $suggestedRemind = (int) $ref->remind_before_days;
            }
        }

        $pic = ($ctx['pic'] ?? null) === '—' ? null : ($ctx['pic'] ?? null);
        if (! $pic && $existing?->responsible_pic) {
            $pic = $existing->responsible_pic;
        }

        $suggestedNext = $this->suggestNextDate(
            $inventoryItemId,
            $suggestedIntervalValue,
            $suggestedIntervalUnit
        );

        return [
            'inventory' => $ctx,
            'pic' => $pic,
            'last_maintenance' => $lastMaintenance,
            'suggested_interval_value' => $suggestedIntervalValue,
            'suggested_interval_unit' => $suggestedIntervalUnit,
            'suggested_interval_label' => $suggestedIntervalValue.' '.(
                MaintenanceIntervalUnit::tryFrom($suggestedIntervalUnit)?->label() ?? $suggestedIntervalUnit
            ),
            'suggested_remind_before_days' => $suggestedRemind,
            'suggested_next_maintenance_date' => $suggestedNext->toDateString(),
            'reminder_starts_at' => $suggestedNext->copy()->subDays($suggestedRemind)->toDateString(),
            'duplicate' => $existing ? [
                'id' => $existing->id,
                'url' => route('maintenance-schedules.show', $existing),
                'next_maintenance_date' => optional($existing->next_maintenance_date)->format('Y-m-d'),
                'interval_label' => $existing->intervalLabel(),
                'remind_before_days' => $existing->remind_before_days,
                'responsible_pic' => $existing->responsible_pic,
            ] : null,
            'existing_schedules' => $existingAny->map(fn (MaintenanceSchedule $s) => [
                'id' => $s->id,
                'type' => $s->maintenance_type?->value,
                'type_label' => $s->maintenance_type?->label(),
                'interval_label' => $s->intervalLabel(),
                'next_maintenance_date' => optional($s->next_maintenance_date)->format('Y-m-d'),
                'remind_before_days' => $s->remind_before_days,
                'pic' => $s->responsible_pic,
                'is_active' => $s->is_active,
                'url' => route('maintenance-schedules.show', $s),
            ])->values()->all(),
            'timeline' => $this->buildTimeline($inventoryItemId),
        ];
    }

    /**
     * Suggest next maintenance date from last work / last cycle / today + interval.
     */
    public function suggestNextDate(
        int $inventoryItemId,
        int $intervalValue,
        MaintenanceIntervalUnit|string $intervalUnit,
        ?Carbon $fromOverride = null,
    ): Carbon {
        if ($fromOverride) {
            return $this->computeNextDate($fromOverride, $intervalValue, $intervalUnit);
        }

        $base = now()->startOfDay();

        $lastCompleted = MaintenanceSchedule::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->whereNotNull('last_completed_at')
            ->orderByDesc('last_completed_at')
            ->value('last_completed_at');

        if ($lastCompleted) {
            $base = Carbon::parse($lastCompleted)->startOfDay();
        } else {
            $lastService = Maintenance::query()
                ->where('inventory_item_id', $inventoryItemId)
                ->whereIn('status', [
                    MaintenanceStatus::Completed->value,
                    MaintenanceStatus::InProgress->value,
                    MaintenanceStatus::Waiting->value,
                ])
                ->orderByDesc('service_date')
                ->value('service_date');

            if ($lastService) {
                $base = Carbon::parse($lastService)->startOfDay();
            }
        }

        return $this->computeNextDate($base, $intervalValue, $intervalUnit);
    }

    /**
     * Unified timeline: past WOs + schedule reminder/next markers.
     *
     * @return list<array<string, mixed>>
     */
    public function buildTimeline(int $inventoryItemId, ?MaintenanceSchedule $focus = null): array
    {
        unset($focus); // reserved for future schedule-scoped filtering

        $events = [];

        $maintenances = Maintenance::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->orderByDesc('service_date')
            ->limit(12)
            ->get(['id', 'maintenance_number', 'maintenance_type', 'status', 'service_date', 'technician', 'completed_at']);

        foreach ($maintenances as $m) {
            $events[] = [
                'kind' => 'work_order',
                'date' => optional($m->service_date)->format('Y-m-d'),
                'title' => $m->maintenance_number,
                'subtitle' => ($m->maintenance_type?->label() ?? 'WO').' · '.($m->status?->label() ?? ''),
                'meta' => $m->technician ?: null,
                'url' => route('maintenances.show', $m),
                'tone' => $m->status === MaintenanceStatus::Completed ? 'success' : 'primary',
            ];
        }

        $schedules = MaintenanceSchedule::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->orderBy('next_maintenance_date')
            ->get();

        foreach ($schedules as $s) {
            if ($s->last_completed_at) {
                $events[] = [
                    'kind' => 'cycle_completed',
                    'date' => $s->last_completed_at->toDateString(),
                    'title' => 'Siklus selesai',
                    'subtitle' => ($s->maintenance_type?->label() ?? 'Jadwal').' · '.$s->intervalLabel(),
                    'meta' => 'Jadwal #'.$s->id,
                    'url' => route('maintenance-schedules.show', $s),
                    'tone' => 'secondary',
                ];
            }

            if ($s->is_active && $s->next_maintenance_date) {
                $events[] = [
                    'kind' => 'reminder',
                    'date' => $s->reminderStartsAt()->toDateString(),
                    'title' => 'Mulai pengingat',
                    'subtitle' => $s->remind_before_days.' hari sebelum jatuh tempo',
                    'meta' => $s->maintenance_type?->label(),
                    'url' => route('maintenance-schedules.show', $s),
                    'tone' => 'warning',
                ];
                $events[] = [
                    'kind' => 'next_due',
                    'date' => $s->next_maintenance_date->toDateString(),
                    'title' => 'Jadwal berikutnya',
                    'subtitle' => ($s->maintenance_type?->label() ?? 'Preventif').' · '.$s->intervalLabel(),
                    'meta' => $s->isOverdue() ? 'Terlambat' : ($s->isDueToday() ? 'Hari ini' : null),
                    'url' => route('maintenance-schedules.show', $s),
                    'tone' => $s->isOverdue() ? 'danger' : 'info',
                ];
            }
        }

        usort($events, function (array $a, array $b) {
            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });

        return array_values($events);
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

    /**
     * @return array<string, mixed>|null
     */
    protected function lastMaintenanceSummary(int $inventoryItemId): ?array
    {
        $m = Maintenance::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->first([
                'id', 'maintenance_number', 'maintenance_type', 'status',
                'service_date', 'technician', 'completed_at',
            ]);

        if (! $m) {
            return null;
        }

        return [
            'id' => $m->id,
            'maintenance_number' => $m->maintenance_number,
            'type_label' => $m->maintenance_type?->label(),
            'status_label' => $m->status?->label(),
            'service_date' => optional($m->service_date)->format('Y-m-d'),
            'technician' => $m->technician ?: '—',
            'url' => route('maintenances.show', $m),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeSchedulePayload(array $data): array
    {
        $itemId = (int) ($data['inventory_item_id'] ?? 0);
        $intervalValue = (int) ($data['interval_value'] ?? 3);
        $intervalUnit = $data['interval_unit'] ?? MaintenanceIntervalUnit::Months->value;

        if (empty($data['next_maintenance_date'])) {
            $data['next_maintenance_date'] = $this->suggestNextDate(
                $itemId,
                $intervalValue,
                $intervalUnit
            )->toDateString();
        }

        if (empty($data['responsible_pic']) && $itemId > 0) {
            $ctx = $this->inventorySearch->detail($itemId);
            $pic = $ctx['pic'] ?? null;
            if ($pic && $pic !== '—') {
                $data['responsible_pic'] = $pic;
            }
        }

        return $data;
    }
}
