<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Campus;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
     * Shared list filters (search / campus / type). Status & card filters applied separately.
     *
     * @param  array{q?: string|null, campus_id?: int|null, type?: string|null}  $filters
     */
    public function applySharedFilters(Builder $query, array $filters): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('responsible_pic', 'like', $term)
                    ->orWhereHas('inventoryItem', function (Builder $item) use ($term) {
                        $item->where('inventory_number', 'like', $term)
                            ->orWhere('name', 'like', $term);
                    });
            });
        }

        $campusId = (int) ($filters['campus_id'] ?? 0);
        if ($campusId > 0) {
            $query->forCampus($campusId);
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $query->where('maintenance_type', $type);
        }

        return $query;
    }

    /**
     * @param  array{q?: string|null, campus_id?: int|null, type?: string|null}  $filters
     * @return array{due_today: int, due_this_week: int, overdue: int, active: int}
     */
    public function summaryCounts(array $filters = []): array
    {
        $base = MaintenanceSchedule::query();
        $this->applySharedFilters($base, $filters);

        return [
            'due_today' => (clone $base)->dueToday()->count(),
            'due_this_week' => (clone $base)->dueThisWeek()->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'active' => (clone $base)->active()->count(),
        ];
    }

    /**
     * Per-campus schedule totals for the index summary strip (respects shared filters).
     *
     * @param  array{q?: string|null, campus_id?: int|null, type?: string|null, status?: string|null, filter?: string|null}  $filters
     * @return list<array{id: int, name: string, total: int}>
     */
    public function countsByCampus(array $filters = []): array
    {
        $query = MaintenanceSchedule::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'maintenance_schedules.inventory_item_id')
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->join('campuses', 'campuses.id', '=', 'buildings.campus_id')
            ->whereNull('inventory_items.deleted_at')
            ->whereNull('rooms.deleted_at')
            ->whereNull('floors.deleted_at')
            ->whereNull('buildings.deleted_at')
            ->whereNull('campuses.deleted_at');

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('maintenance_schedules.responsible_pic', 'like', $term)
                    ->orWhere('inventory_items.inventory_number', 'like', $term)
                    ->orWhere('inventory_items.name', 'like', $term);
            });
        }

        $campusId = (int) ($filters['campus_id'] ?? 0);
        if ($campusId > 0) {
            $query->where('campuses.id', $campusId);
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $query->where('maintenance_schedules.maintenance_type', $type);
        }

        $this->applyStatusAndCardFilter($query, $filters, tablePrefix: 'maintenance_schedules.');

        return $query
            ->groupBy('campuses.id', 'campuses.name')
            ->orderBy('campuses.name')
            ->selectRaw('campuses.id as id, campuses.name as name, COUNT(maintenance_schedules.id) as total')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Apply status dropdown + summary-card filter to a schedule query.
     *
     * @param  array{status?: string|null, filter?: string|null}  $filters
     */
    public function applyStatusAndCardFilter(Builder $query, array $filters, string $tablePrefix = ''): Builder
    {
        $card = (string) ($filters['filter'] ?? 'all');
        $status = (string) ($filters['status'] ?? '');

        // Summary cards take precedence when set to a due window.
        if (in_array($card, ['due_today', 'due_week', 'overdue', 'inactive'], true)) {
            return match ($card) {
                'due_today' => $query->where($tablePrefix.'is_active', true)
                    ->whereDate($tablePrefix.'next_maintenance_date', now()->toDateString()),
                'due_week' => $query->where($tablePrefix.'is_active', true)
                    ->whereDate($tablePrefix.'next_maintenance_date', '>=', now()->startOfDay()->toDateString())
                    ->whereDate($tablePrefix.'next_maintenance_date', '<=', now()->endOfWeek()->toDateString()),
                'overdue' => $query->where($tablePrefix.'is_active', true)
                    ->whereDate($tablePrefix.'next_maintenance_date', '<', now()->toDateString()),
                'inactive' => $query->where($tablePrefix.'is_active', false),
                default => $query,
            };
        }

        return match ($status) {
            'all' => $query,
            'active' => $query->where($tablePrefix.'is_active', true),
            'inactive' => $query->where($tablePrefix.'is_active', false),
            'overdue' => $query->where($tablePrefix.'is_active', true)
                ->whereDate($tablePrefix.'next_maintenance_date', '<', now()->toDateString()),
            // Default landing / card "Jadwal Aktif": only active schedules.
            default => $query->where($tablePrefix.'is_active', true),
        };
    }

    /**
     * Active campuses for filter dropdown (id + name only).
     *
     * @return Collection<int, Campus>
     */
    public function activeCampuses(): Collection
    {
        return Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
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
            ->with([
                'inventoryItem:id,inventory_number,name,room_id',
                'inventoryItem.room:id,floor_id',
                'inventoryItem.room.floor:id,building_id',
                'inventoryItem.room.floor.building:id,campus_id',
                'inventoryItem.room.floor.building.campus:id,name',
            ])
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
