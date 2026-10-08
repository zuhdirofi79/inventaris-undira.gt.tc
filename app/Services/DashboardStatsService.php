<?php

namespace App\Services;

use App\Support\InventoryConditionCatalog;
use App\Enums\InventoryStatus;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\Room;
use App\Models\Supplier;
use App\Models\Vendor;
use App\Support\InventarisCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Efficient dashboard aggregates from live inventory + location master data.
 * All queries use COUNT/SUM/GROUP BY — no per-row loops.
 */
class DashboardStatsService
{
    public function __construct(
        protected MaintenanceScheduleService $schedules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return Cache::remember(InventarisCache::key('dashboard.summary.v3'), InventarisCache::TTL, function () {
            $statusCounts = InventoryItem::query()
                ->toBase()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $conditionCol = InventoryItem::query()->getConnection()->getQueryGrammar()->wrap('condition');
            $conditionCounts = InventoryItem::query()
                ->toBase()
                ->selectRaw("{$conditionCol} as cond, COUNT(*) as total")
                ->groupBy('condition')
                ->pluck('total', 'cond');

            $scheduleSummary = $this->schedules->summaryCounts();
            $goodCondition = (int) ($conditionCounts['baru'] ?? 0)
                + (int) ($conditionCounts['bagus'] ?? 0)
                + (int) ($conditionCounts['BAIK'] ?? 0);
            $minorDamage = (int) ($conditionCounts['rusak_ringan'] ?? 0);
            $majorDamage = (int) ($conditionCounts['rusak_berat'] ?? 0)
                + (int) ($conditionCounts['RUSAK'] ?? 0);
            $borrowed = (int) ($statusCounts[InventoryStatus::Dipinjam->value] ?? 0);
            $maintenance = (int) ($statusCounts[InventoryStatus::Maintenance->value] ?? 0);
            $lost = (int) ($statusCounts[InventoryStatus::Hilang->value] ?? 0);
            $auctioned = (int) ($statusCounts[InventoryStatus::Dilelang->value] ?? 0);
            $destroyed = (int) ($statusCounts[InventoryStatus::Dimusnahkan->value] ?? 0);
            $active = (int) ($statusCounts[InventoryStatus::Aktif->value] ?? 0);
            $damaged = collect(InventoryStatus::damagedValues())
                ->sum(fn (string $value) => (int) ($statusCounts[$value] ?? 0));
            $totalInventory = (int) array_sum($statusCounts->all());
            $preventiveDue = (int) ($scheduleSummary['due_today'] + $scheduleSummary['due_this_week']);

            $approvalStats = $this->borrowingApprovalStats();

            return [
                'total_campuses' => Campus::query()->where('is_active', true)->count(),
                'total_buildings' => Building::query()->where('is_active', true)->count(),
                'total_floors' => Floor::query()->where('is_active', true)->count(),
                'total_rooms' => Room::query()->where('is_active', true)->count(),
                'total_org_units' => \App\Models\OrgUnit::query()->where('is_active', true)->count(),
                'total_categories' => \App\Models\Category::query()->where('is_active', true)->count(),
                'total_sub_categories' => \App\Models\SubCategory::query()->where('is_active', true)->count(),
                'total_users' => \App\Models\User::query()->count(),
                'total_suppliers' => Supplier::query()->where('is_active', true)->count(),
                'total_vendors' => Vendor::query()->where('is_active', true)->count(),
                'total_inventory' => $totalInventory,
                'active_inventory' => $active,
                'available' => $active,
                'borrowed' => $borrowed,
                'maintenance' => $maintenance,
                'damaged' => $damaged,
                'lost' => $lost,
                'good_condition' => $goodCondition,
                'minor_damage' => $minorDamage,
                'major_damage' => $majorDamage,
                'preventive_due' => $preventiveDue,
                'auctioned' => $auctioned,
                'destroyed' => $destroyed,
                'pending_approval' => $approvalStats['pending_approval'],
                'approved_today' => $approvalStats['approved_today'],
                'rejected_today' => $approvalStats['rejected_today'],
                'active_loans' => $approvalStats['active_loans'],
                'overdue_loans' => $approvalStats['overdue_loans'],
                'condition_cards' => InventoryConditionCatalog::cards($conditionCounts),
                'widgets' => [
                    'total_inventory' => $totalInventory,
                    'good_condition' => $goodCondition,
                    'minor_damage' => $minorDamage,
                    'major_damage' => $majorDamage,
                    'borrowed' => $borrowed,
                    'maintenance' => $maintenance,
                    'preventive_due' => $preventiveDue,
                    'lost' => $lost,
                    'auctioned' => $auctioned,
                    'destroyed' => $destroyed,
                    'pending_approval' => $approvalStats['pending_approval'],
                    'approved_today' => $approvalStats['approved_today'],
                    'rejected_today' => $approvalStats['rejected_today'],
                    'active_loans' => $approvalStats['active_loans'],
                    'overdue_loans' => $approvalStats['overdue_loans'],
                ],
                'campus_stats' => $this->campusStats(),
                'charts' => $this->chartData($statusCounts),
            ];
        });
    }

    /**
     * @return array{pending_approval:int,approved_today:int,rejected_today:int,active_loans:int,overdue_loans:int}
     */
    protected function borrowingApprovalStats(): array
    {
        $today = now()->toDateString();

        return [
            'pending_approval' => (int) Borrowing::query()
                ->where('status', \App\Enums\BorrowingStatus::MenungguPersetujuan)
                ->count(),
            'approved_today' => (int) Borrowing::query()
                ->whereDate('approved_at', $today)
                ->count(),
            'rejected_today' => (int) Borrowing::query()
                ->whereDate('rejected_at', $today)
                ->count(),
            'active_loans' => (int) Borrowing::query()
                ->whereIn('status', [
                    \App\Enums\BorrowingStatus::Disetujui,
                    \App\Enums\BorrowingStatus::Dipinjam,
                ])
                ->count(),
            'overdue_loans' => (int) Borrowing::query()
                ->where('status', \App\Enums\BorrowingStatus::Terlambat)
                ->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function campusStats(): array
    {
        return Cache::remember(InventarisCache::key('campus.stats'), InventarisCache::TTL, function () {
            $campuses = Campus::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']);

            if ($campuses->isEmpty()) {
                return [];
            }

            $campusIds = $campuses->pluck('id');

            $locationCounts = $this->locationCountsByCampus($campusIds);
            $inventoryAgg = $this->inventoryAggByCampus($campusIds);
            $statusByCampus = $this->statusByCampus($campusIds);
            $categoryByCampus = $this->categoryTotalsByCampus($campusIds);

            return $campuses->map(function (Campus $campus) use ($locationCounts, $inventoryAgg, $statusByCampus, $categoryByCampus) {
                $id = $campus->id;
                $loc = $locationCounts[$id] ?? ['buildings' => 0, 'floors' => 0, 'rooms' => 0];
                $inv = $inventoryAgg[$id] ?? ['total_units' => 0, 'total_inventory' => 0];
                $status = $statusByCampus[$id] ?? [];
                $cats = $categoryByCampus[$id] ?? [];

                return [
                    'id' => $id,
                    'name' => $campus->name,
                    'code' => $campus->code,
                    'total_inventory' => (int) $inv['total_inventory'],
                    'total_units' => (int) $inv['total_units'],
                    'buildings' => (int) $loc['buildings'],
                    'floors' => (int) $loc['floors'],
                    'rooms' => (int) $loc['rooms'],
                    'available' => (int) ($status[InventoryStatus::Aktif->value] ?? 0),
                    'borrowed' => (int) ($status[InventoryStatus::Dipinjam->value] ?? 0),
                    'maintenance' => (int) ($status[InventoryStatus::Maintenance->value] ?? 0),
                    'damaged' => collect(InventoryStatus::damagedValues())
                        ->sum(fn (string $value) => (int) ($status[$value] ?? 0)),
                    'categories' => $cats,
                ];
            })->values()->all();
        });
    }

    /**
     * Grouped: Category => [subcategories with counts]
     *
     * @return list<array{category: string, category_code: string, total: int, subcategories: list<array{name: string, total: int}>}>
     */
    public function categorySubcategoryBreakdown(): array
    {
        $rows = InventoryItem::query()
            ->join('categories', 'categories.id', '=', 'inventory_items.category_id')
            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'inventory_items.sub_category_id')
            ->selectRaw('categories.id as category_id, categories.code as category_code, categories.name as category_name')
            ->selectRaw('COALESCE(sub_categories.name, \'Tanpa sub\') as sub_name')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('categories.id', 'categories.code', 'categories.name', 'sub_name')
            ->orderBy('categories.name')
            ->orderByDesc('total')
            ->get();

        return $rows->groupBy('category_id')->map(function (Collection $group) {
            $first = $group->first();

            return [
                'category' => $first->category_name,
                'category_code' => $first->category_code,
                'category_id' => (int) $first->category_id,
                'total' => (int) $group->sum('total'),
                'subcategories' => $group->map(fn ($row) => [
                    'name' => $row->sub_name,
                    'total' => (int) $row->total,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, list<array{name: string, code: string, total: int}>>
     */
    public function categoryTotalsByCampus(Collection $campusIds): array
    {
        $rows = InventoryItem::query()
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->join('categories', 'categories.id', '=', 'inventory_items.category_id')
            ->whereIn('buildings.campus_id', $campusIds)
            ->selectRaw('buildings.campus_id, categories.code, categories.name, COUNT(*) as total')
            ->groupBy('buildings.campus_id', 'categories.code', 'categories.name')
            ->orderByDesc('total')
            ->orderBy('categories.name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->campus_id][] = [
                'name' => $row->name,
                'code' => $row->code,
                'total' => (int) $row->total,
            ];
        }

        // Ensure each campus list is sorted by unit count descending (stable for cards).
        foreach ($result as $campusId => $cats) {
            usort($cats, function (array $a, array $b): int {
                return $b['total'] <=> $a['total'] ?: strcmp($a['name'], $b['name']);
            });
            $result[$campusId] = $cats;
        }

        return $result;
    }

    /**
     * @return array<int, list<array{category: string, sub: string, total: int}>>
     */
    public function campusSubcategoryBreakdown(): array
    {
        $rows = InventoryItem::query()
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->join('campuses', 'campuses.id', '=', 'buildings.campus_id')
            ->join('categories', 'categories.id', '=', 'inventory_items.category_id')
            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'inventory_items.sub_category_id')
            ->where('campuses.is_active', true)
            ->selectRaw('campuses.id as campus_id, campuses.name as campus_name')
            ->selectRaw('categories.name as category_name')
            ->selectRaw('COALESCE(sub_categories.name, \'Tanpa sub\') as sub_name')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('campuses.id', 'campuses.name', 'categories.name', 'sub_name')
            ->orderBy('campuses.name')
            ->orderBy('categories.name')
            ->orderByDesc('total')
            ->get();

        return $rows->groupBy('campus_id')->map(function (Collection $campusRows) {
            $campusName = $campusRows->first()->campus_name;

            return [
                'campus_id' => (int) $campusRows->first()->campus_id,
                'campus_name' => $campusName,
                'categories' => $campusRows->groupBy('category_name')->map(function (Collection $catRows, string $catName) {
                    return [
                        'category' => $catName,
                        'total' => (int) $catRows->sum('total'),
                        'subcategories' => $catRows->map(fn ($r) => [
                            'name' => $r->sub_name,
                            'total' => (int) $r->total,
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Active inventory only: Campus → Category → Subcategory → COUNT.
     * Separate from campusStats()/summary() so existing dashboard queries stay unchanged.
     *
     * @return list<array{
     *   campus_id: int,
     *   campus_name: string,
     *   campus_code: string,
     *   total: int,
     *   categories: list<array{
     *     category: string,
     *     total: int,
     *     subcategories: list<array{name: string, total: int}>
     *   }>
     * }>
     */
    public function activeCampusCategorySubcategoryBreakdown(): array
    {
        return Cache::remember(InventarisCache::key('campus.active.cat.sub.v1'), InventarisCache::TTL, function () {
            $campuses = Campus::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);

            if ($campuses->isEmpty()) {
                return [];
            }

            $rows = InventoryItem::query()
                ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
                ->join('floors', 'floors.id', '=', 'rooms.floor_id')
                ->join('buildings', 'buildings.id', '=', 'floors.building_id')
                ->join('campuses', 'campuses.id', '=', 'buildings.campus_id')
                ->join('categories', 'categories.id', '=', 'inventory_items.category_id')
                ->leftJoin('sub_categories', 'sub_categories.id', '=', 'inventory_items.sub_category_id')
                ->where('inventory_items.status', InventoryStatus::Aktif->value)
                ->whereIn('campuses.id', $campuses->pluck('id'))
                ->selectRaw('campuses.id as campus_id')
                ->selectRaw('categories.id as category_id, categories.name as category_name')
                ->selectRaw('COALESCE(sub_categories.name, \'Tanpa sub\') as sub_name')
                ->selectRaw('COUNT(inventory_items.id) as total')
                ->groupBy('campuses.id', 'categories.id', 'categories.name', 'sub_name')
                ->orderBy('categories.name')
                ->orderByDesc('total')
                ->get();

            $byCampus = $rows->groupBy('campus_id');

            return $campuses->map(function (Campus $campus) use ($byCampus) {
                $campusRows = $byCampus->get($campus->id, collect());

                $categories = $campusRows
                    ->groupBy('category_id')
                    ->map(function (Collection $catRows) {
                        $first = $catRows->first();

                        return [
                            'category' => (string) $first->category_name,
                            'total' => (int) $catRows->sum('total'),
                            'subcategories' => $catRows
                                ->map(fn ($row) => [
                                    'name' => (string) $row->sub_name,
                                    'total' => (int) $row->total,
                                ])
                                ->sortByDesc('total')
                                ->values()
                                ->all(),
                        ];
                    })
                    ->sortBy('category')
                    ->values()
                    ->all();

                return [
                    'campus_id' => (int) $campus->id,
                    'campus_name' => $campus->name,
                    'campus_code' => $campus->code,
                    'total' => (int) collect($categories)->sum('total'),
                    'categories' => $categories,
                ];
            })->values()->all();
        });
    }

    /**
     * @return list<array{campus_id: int, campus_name: string, categories: list<array{name: string, code: string, total: int}>}>
     */
    public function campusCategoryBreakdown(): array
    {
        $campuses = Campus::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $byCampus = $this->categoryTotalsByCampus($campuses->pluck('id'));

        return $campuses->map(fn (Campus $c) => [
            'campus_id' => $c->id,
            'campus_name' => $c->name,
            'categories' => $byCampus[$c->id] ?? [],
        ])->values()->all();
    }

    /**
     * Chart payloads must be plain arrays (never Eloquent Collections).
     * Caching Eloquent Collections in dashboard.summary caused
     * __PHP_Incomplete_Class after refresh → @json broke → Chart.js blank.
     *
     * @param  Collection<int, int|string>|array<string, int|string>|null  $statusCounts
     * @return array<string, list<array{label: string, total: int}>>
     */
    public function chartData($statusCounts = null): array
    {
        $statusCounts ??= InventoryItem::query()
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byCampus = InventoryItem::query()
            ->join('rooms', 'inventory_items.room_id', '=', 'rooms.id')
            ->join('floors', 'rooms.floor_id', '=', 'floors.id')
            ->join('buildings', 'floors.building_id', '=', 'buildings.id')
            ->join('campuses', 'buildings.campus_id', '=', 'campuses.id')
            ->toBase()
            ->selectRaw('campuses.name as label, COUNT(*) as total')
            ->groupBy('campuses.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $byCategory = InventoryItem::query()
            ->join('categories', 'inventory_items.category_id', '=', 'categories.id')
            ->toBase()
            ->selectRaw('categories.name as label, COUNT(*) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $byStatus = [];
        foreach ($statusCounts as $key => $total) {
            $enum = InventoryStatus::tryFrom((string) $key);
            $byStatus[] = [
                'label' => $enum?->label() ?? str_replace('_', ' ', (string) $key),
                'total' => (int) $total,
            ];
        }

        $conditionCol = InventoryItem::query()->getConnection()->getQueryGrammar()->wrap('condition');
        $byCondition = InventoryItem::query()
            ->toBase()
            ->selectRaw("{$conditionCol} as label_key, COUNT(*) as total")
            ->groupBy('condition')
            ->get()
            ->map(function ($row) {
                return [
                    'label' => InventoryConditionCatalog::label((string) $row->label_key),
                    'total' => (int) $row->total,
                ];
            })
            ->values()
            ->all();

        return [
            'by_campus' => $byCampus,
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'by_condition' => $byCondition,
            'borrowings_monthly' => $this->monthlySeries(Borrowing::query(), 'borrow_date', 6),
            'maintenances_monthly' => $this->monthlySeries(Maintenance::query(), 'service_date', 6),
        ];
    }

    /**
     * Last N calendar months with zero-filled totals.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return list<array{label: string, total: int}>
     */
    protected function monthlySeries($query, string $dateColumn, int $months = 6): array
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

    protected function monthKeyExpression(string $column): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * @param  Collection<int, int>  $campusIds
     * @return array<int, array{buildings: int, floors: int, rooms: int}>
     */
    protected function locationCountsByCampus(Collection $campusIds): array
    {
        $buildings = Building::query()
            ->where('is_active', true)
            ->whereIn('campus_id', $campusIds)
            ->selectRaw('campus_id, COUNT(*) as total')
            ->groupBy('campus_id')
            ->pluck('total', 'campus_id');

        $floors = Floor::query()
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->where('floors.is_active', true)
            ->where('buildings.is_active', true)
            ->whereIn('buildings.campus_id', $campusIds)
            ->selectRaw('buildings.campus_id, COUNT(*) as total')
            ->groupBy('buildings.campus_id')
            ->pluck('total', 'campus_id');

        $rooms = Room::query()
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->where('rooms.is_active', true)
            ->where('floors.is_active', true)
            ->where('buildings.is_active', true)
            ->whereIn('buildings.campus_id', $campusIds)
            ->selectRaw('buildings.campus_id, COUNT(*) as total')
            ->groupBy('buildings.campus_id')
            ->pluck('total', 'campus_id');

        $result = [];
        foreach ($campusIds as $id) {
            $result[(int) $id] = [
                'buildings' => (int) ($buildings[$id] ?? 0),
                'floors' => (int) ($floors[$id] ?? 0),
                'rooms' => (int) ($rooms[$id] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, int>  $campusIds
     * @return array<int, array{total_units: int, total_inventory: int}>
     */
    protected function inventoryAggByCampus(Collection $campusIds): array
    {
        $rows = InventoryItem::query()
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->whereIn('buildings.campus_id', $campusIds)
            ->selectRaw('buildings.campus_id')
            ->selectRaw('COUNT(inventory_items.id) as total_units')
            ->selectRaw('COUNT(DISTINCT COALESCE(inventory_items.group_key, inventory_items.name)) as total_inventory')
            ->groupBy('buildings.campus_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->campus_id] = [
                'total_units' => (int) $row->total_units,
                'total_inventory' => (int) $row->total_inventory,
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, int>  $campusIds
     * @return array<int, array<string, int>>
     */
    protected function statusByCampus(Collection $campusIds): array
    {
        $rows = InventoryItem::query()
            ->toBase()
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->whereIn('buildings.campus_id', $campusIds)
            ->selectRaw('buildings.campus_id, inventory_items.status, COUNT(*) as total')
            ->groupBy('buildings.campus_id', 'inventory_items.status')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->campus_id][(string) $row->status] = (int) $row->total;
        }

        return $result;
    }
}
