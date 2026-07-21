<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use Illuminate\Support\Collection;

/**
 * Efficient dashboard aggregates from live inventory + location master data.
 * All queries use COUNT/SUM/GROUP BY — no per-row loops.
 */
class DashboardStatsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $statusCounts = InventoryItem::query()
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalValue = (float) InventoryItem::query()->sum('price');

        return [
            'total_campuses' => Campus::query()->where('is_active', true)->count(),
            'total_buildings' => Building::query()->where('is_active', true)->count(),
            'total_floors' => Floor::query()->where('is_active', true)->count(),
            'total_rooms' => Room::query()->where('is_active', true)->count(),
            'total_inventory' => InventoryItem::query()->count(),
            'total_value' => $totalValue,
            'active_inventory' => (int) ($statusCounts[InventoryStatus::Aktif->value] ?? 0),
            'borrowed' => (int) ($statusCounts[InventoryStatus::Dipinjam->value] ?? 0),
            'maintenance' => (int) ($statusCounts[InventoryStatus::Maintenance->value] ?? 0),
            'damaged' => (int) ($statusCounts[InventoryStatus::RusakRingan->value] ?? 0)
                + (int) ($statusCounts[InventoryStatus::RusakBerat->value] ?? 0),
            'lost' => (int) ($statusCounts[InventoryStatus::Hilang->value] ?? 0),
            'campus_stats' => $this->campusStats(),
            'category_breakdown' => $this->categorySubcategoryBreakdown(),
            'campus_category_breakdown' => $this->campusCategoryBreakdown(),
            'campus_subcategory_breakdown' => $this->campusSubcategoryBreakdown(),
            'charts' => $this->chartData($statusCounts),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function campusStats(): array
    {
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
            $inv = $inventoryAgg[$id] ?? ['total_units' => 0, 'total_inventory' => 0, 'total_value' => 0];
            $status = $statusByCampus[$id] ?? [];
            $cats = $categoryByCampus[$id] ?? [];

            return [
                'id' => $id,
                'name' => $campus->name,
                'code' => $campus->code,
                'total_inventory' => (int) $inv['total_inventory'],
                'total_units' => (int) $inv['total_units'],
                'total_value' => (float) $inv['total_value'],
                'buildings' => (int) $loc['buildings'],
                'floors' => (int) $loc['floors'],
                'rooms' => (int) $loc['rooms'],
                'available' => (int) ($status[InventoryStatus::Aktif->value] ?? 0),
                'borrowed' => (int) ($status[InventoryStatus::Dipinjam->value] ?? 0),
                'maintenance' => (int) ($status[InventoryStatus::Maintenance->value] ?? 0),
                'damaged' => (int) ($status[InventoryStatus::RusakRingan->value] ?? 0)
                    + (int) ($status[InventoryStatus::RusakBerat->value] ?? 0),
                'categories' => $cats,
            ];
        })->values()->all();
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
                'total' => (int) $group->sum('total'),
                'subcategories' => $group->map(fn ($row) => [
                    'name' => $row->sub_name,
                    'total' => (int) $row->total,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, list<array{name: string, code: string, total: int, total_value: float}>>
     */
    public function categoryTotalsByCampus(Collection $campusIds): array
    {
        $rows = InventoryItem::query()
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->join('categories', 'categories.id', '=', 'inventory_items.category_id')
            ->whereIn('buildings.campus_id', $campusIds)
            ->selectRaw('buildings.campus_id, categories.code, categories.name, COUNT(*) as total, COALESCE(SUM(inventory_items.price), 0) as total_value')
            ->groupBy('buildings.campus_id', 'categories.code', 'categories.name')
            ->orderBy('categories.name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->campus_id][] = [
                'name' => $row->name,
                'code' => $row->code,
                'total' => (int) $row->total,
                'total_value' => (float) $row->total_value,
            ];
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
     * @return list<array{campus_id: int, campus_name: string, categories: list<array{name: string, code: string, total: int, total_value: float}>}>
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
     * @param  Collection<int, int|string>  $statusCounts
     * @return array<string, mixed>
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
            ->selectRaw('campuses.name as label, COUNT(*) as total')
            ->groupBy('campuses.name')
            ->orderByDesc('total')
            ->get();

        $valueByCampus = InventoryItem::query()
            ->join('rooms', 'inventory_items.room_id', '=', 'rooms.id')
            ->join('floors', 'rooms.floor_id', '=', 'floors.id')
            ->join('buildings', 'floors.building_id', '=', 'buildings.id')
            ->join('campuses', 'buildings.campus_id', '=', 'campuses.id')
            ->selectRaw('campuses.name as label, COALESCE(SUM(inventory_items.price), 0) as total')
            ->groupBy('campuses.name')
            ->orderByDesc('total')
            ->get();

        $byCategory = InventoryItem::query()
            ->join('categories', 'inventory_items.category_id', '=', 'categories.id')
            ->selectRaw('categories.name as label, COUNT(*) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        $bySubcategory = InventoryItem::query()
            ->join('sub_categories', 'inventory_items.sub_category_id', '=', 'sub_categories.id')
            ->selectRaw('sub_categories.name as label, COUNT(*) as total')
            ->groupBy('sub_categories.name')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $byStatus = [];
        foreach ($statusCounts as $key => $total) {
            $enum = InventoryStatus::tryFrom((string) $key);
            $byStatus[] = [
                'label' => $enum?->label() ?? str_replace('_', ' ', (string) $key),
                'total' => (int) $total,
            ];
        }

        return [
            'by_campus' => $byCampus,
            'by_category' => $byCategory,
            'by_subcategory' => $bySubcategory,
            'by_status' => $byStatus,
            'value_by_campus' => $valueByCampus,
        ];
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
     * @return array<int, array{total_units: int, total_inventory: int, total_value: float}>
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
            ->selectRaw('COALESCE(SUM(inventory_items.price), 0) as total_value')
            ->groupBy('buildings.campus_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->campus_id] = [
                'total_units' => (int) $row->total_units,
                'total_inventory' => (int) $row->total_inventory,
                'total_value' => (float) $row->total_value,
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
