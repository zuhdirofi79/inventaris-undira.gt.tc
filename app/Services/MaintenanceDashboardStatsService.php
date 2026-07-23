<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use Illuminate\Support\Facades\DB;

class MaintenanceDashboardStatsService
{
    public function __construct(
        protected MaintenanceScheduleService $schedules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $scheduleSummary = $this->schedules->summaryCounts();

        $statusCounts = Maintenance::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $completedToday = Maintenance::query()
            ->where('status', MaintenanceStatus::Completed)
            ->whereDate('completed_at', now()->toDateString())
            ->count();

        $completedMonth = Maintenance::query()
            ->where('status', MaintenanceStatus::Completed)
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->count();

        $costThisMonth = (float) Maintenance::query()
            ->whereMonth('service_date', now()->month)
            ->whereYear('service_date', now()->year)
            ->sum('cost');

        $avgRepairHours = $this->averageRepairHours();

        return [
            'cards' => [
                'total' => Maintenance::query()->count(),
                'waiting' => (int) ($statusCounts[MaintenanceStatus::Waiting->value] ?? 0),
                'on_progress' => (int) ($statusCounts[MaintenanceStatus::InProgress->value] ?? 0),
                'completed_today' => $completedToday,
                'completed_month' => $completedMonth,
                'preventive_due' => $scheduleSummary['due_today'] + $scheduleSummary['due_this_week'],
                'overdue_schedules' => $scheduleSummary['overdue'],
                'cost_this_month' => $costThisMonth,
                'avg_repair_hours' => $avgRepairHours,
            ],
            'charts' => $this->charts(),
            'tables' => [
                'recent' => Maintenance::query()
                    ->with(['inventoryItem:id,inventory_number,name', 'vendor:id,name'])
                    ->latest('service_date')
                    ->latest('id')
                    ->limit(10)
                    ->get(),
                'upcoming' => MaintenanceSchedule::query()
                    ->active()
                    ->with(['inventoryItem:id,inventory_number,name'])
                    ->whereDate('next_maintenance_date', '>=', now()->toDateString())
                    ->orderBy('next_maintenance_date')
                    ->limit(10)
                    ->get(),
                'overdue' => MaintenanceSchedule::query()
                    ->overdue()
                    ->with(['inventoryItem:id,inventory_number,name'])
                    ->orderBy('next_maintenance_date')
                    ->limit(10)
                    ->get(),
            ],
            'schedule_summary' => $scheduleSummary,
        ];
    }

    /**
     * @return array<string, list<array{label: string, total: float|int}>>
     */
    public function charts(): array
    {
        return [
            'monthly_trend' => $this->monthlyCountSeries(Maintenance::query(), 'service_date', 12),
            'by_category' => $this->byCategory(),
            'by_campus' => $this->byCampus(),
            'cost_monthly' => $this->monthlySumSeries(Maintenance::query(), 'service_date', 'cost', 12),
            'top_assets' => $this->topRepairedAssets(10),
        ];
    }

    protected function averageRepairHours(): ?float
    {
        $rows = Maintenance::query()
            ->where('status', MaintenanceStatus::Completed)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get(['started_at', 'completed_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $hours = $rows->map(function (Maintenance $m) {
            return max(0, $m->started_at->diffInMinutes($m->completed_at) / 60);
        });

        return round((float) $hours->avg(), 1);
    }

    /**
     * @return list<array{label: string, total: int}>
     */
    protected function byCategory(): array
    {
        return Maintenance::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'maintenances.inventory_item_id')
            ->leftJoin('categories', 'categories.id', '=', 'inventory_items.category_id')
            ->selectRaw("COALESCE(categories.name, 'Tanpa Kategori') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'total' => (int) $r->total])
            ->all();
    }

    /**
     * @return list<array{label: string, total: int}>
     */
    protected function byCampus(): array
    {
        return Maintenance::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'maintenances.inventory_item_id')
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->join('campuses', 'campuses.id', '=', 'buildings.campus_id')
            ->selectRaw('campuses.name as label, COUNT(*) as total')
            ->groupBy('campuses.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'total' => (int) $r->total])
            ->all();
    }

    /**
     * @return list<array{label: string, total: int}>
     */
    protected function topRepairedAssets(int $limit = 10): array
    {
        $labelExpr = match (DB::getDriverName()) {
            'sqlite' => "inventory_items.inventory_number || ' — ' || inventory_items.name",
            'pgsql' => "inventory_items.inventory_number || ' — ' || inventory_items.name",
            default => "CONCAT(inventory_items.inventory_number, ' — ', inventory_items.name)",
        };

        return Maintenance::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'maintenances.inventory_item_id')
            ->selectRaw("{$labelExpr} as label, COUNT(*) as total")
            ->groupBy('inventory_items.id', 'inventory_items.inventory_number', 'inventory_items.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'total' => (int) $r->total])
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return list<array{label: string, total: int}>
     */
    protected function monthlyCountSeries($query, string $dateColumn, int $months = 12): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();
        $monthExpr = $this->monthKeyExpression($dateColumn);

        $rows = (clone $query)
            ->where($dateColumn, '>=', $start->toDateString())
            ->toBase()
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');
            $series[] = [
                'label' => $month->translatedFormat('M Y'),
                'total' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return list<array{label: string, total: float}>
     */
    protected function monthlySumSeries($query, string $dateColumn, string $sumColumn, int $months = 12): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();
        $monthExpr = $this->monthKeyExpression($dateColumn);

        $rows = (clone $query)
            ->where($dateColumn, '>=', $start->toDateString())
            ->toBase()
            ->selectRaw("{$monthExpr} as ym, COALESCE(SUM({$sumColumn}), 0) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');
            $series[] = [
                'label' => $month->translatedFormat('M Y'),
                'total' => (float) ($rows[$key] ?? 0),
            ];
        }

        return $series;
    }

    protected function monthKeyExpression(string $column): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * Damaged inventory counts for report shortcut cards.
     *
     * @return array{rusak: int, tidak_dapat_diperbaiki: int, perlu_penggantian: int}
     */
    public function damagedInventoryCounts(): array
    {
        $counts = InventoryItem::query()
            ->whereIn('status', [
                InventoryStatus::RusakRingan->value,
                InventoryStatus::RusakBerat->value,
                InventoryStatus::TidakDapatDiperbaiki->value,
                InventoryStatus::PerluPenggantian->value,
            ])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'rusak' => (int) ($counts[InventoryStatus::RusakRingan->value] ?? 0)
                + (int) ($counts[InventoryStatus::RusakBerat->value] ?? 0),
            'tidak_dapat_diperbaiki' => (int) ($counts[InventoryStatus::TidakDapatDiperbaiki->value] ?? 0),
            'perlu_penggantian' => (int) ($counts[InventoryStatus::PerluPenggantian->value] ?? 0),
        ];
    }
}
