<?php

namespace App\Services;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds hierarchical inventory reports from the same filtered InventoryItem
 * source used by Dashboard, Data Inventaris, and Room Detail.
 */
class InventoryReportService
{
    public function __construct(
        protected InventoryFilterService $filterService,
    ) {}

    /**
     * @return array{
     *     items: Collection<int, InventoryItem>,
     *     tree: list<array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     filters: array<string, string|null>,
     *     meta: array{printed_at: string, user_name: string, title: string}
     * }
     */
    public function build(Request $request): array
    {
        $items = $this->fetchItems($request);
        $tree = $this->buildTree($items);

        return [
            'items' => $items,
            'tree' => $tree,
            'summary' => $this->buildSummary($items),
            'filters' => $this->describeFilters($request),
            'meta' => [
                'printed_at' => now()->translatedFormat('d F Y H:i'),
                'user_name' => $request->user()?->name ?? 'Sistem',
                'title' => 'Laporan Inventaris Universitas Dian Nusantara',
            ],
        ];
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    public const MAX_ROWS = 5000;

    public function fetchItems(Request $request): Collection
    {
        return $this->filterService
            ->apply($request)
            ->with([
                'category:id,code,name',
                'subCategory:id,category_id,code,name',
                'room:id,floor_id,code,name',
                'room.floor:id,building_id,code,name,floor_number',
                'room.floor.building:id,campus_id,code,name',
                'room.floor.building.campus:id,code,name',
                'supplier:id,name',
                'vendor:id,name',
            ])
            ->limit(self::MAX_ROWS)
            ->get()
            ->sortBy([
                fn (InventoryItem $i) => mb_strtolower($i->room?->floor?->building?->campus?->name ?? 'zzz'),
                fn (InventoryItem $i) => mb_strtolower($i->room?->floor?->building?->name ?? 'zzz'),
                fn (InventoryItem $i) => $i->room?->floor?->floor_number ?? 9999,
                fn (InventoryItem $i) => mb_strtolower($i->room?->name ?? 'zzz'),
                fn (InventoryItem $i) => mb_strtolower($i->category?->name ?? 'zzz'),
                fn (InventoryItem $i) => mb_strtolower($i->subCategory?->name ?? 'zzz'),
                fn (InventoryItem $i) => mb_strtolower($i->name),
                fn (InventoryItem $i) => $i->inventory_number,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, InventoryItem>  $items
     * @return list<array<string, mixed>>
     */
    public function buildTree(Collection $items): array
    {
        $campuses = [];

        foreach ($items as $item) {
            $room = $item->room;
            $floor = $room?->floor;
            $building = $floor?->building;
            $campus = $building?->campus;

            $campusKey = (string) ($campus?->id ?? 0);
            $buildingKey = (string) ($building?->id ?? 0);
            $floorKey = (string) ($floor?->id ?? 0);
            $roomKey = (string) ($room?->id ?? 0);
            $categoryKey = (string) ($item->category_id ?? 0);
            $subKey = (string) ($item->sub_category_id ?? 0);

            if (! isset($campuses[$campusKey])) {
                $campuses[$campusKey] = [
                    'campus' => $campus,
                    'label' => $campus?->name ?? 'Tanpa Kampus',
                    'totals' => $this->emptyTotals(),
                    'by_category' => [],
                    'buildings' => [],
                ];
            }

            if (! isset($campuses[$campusKey]['buildings'][$buildingKey])) {
                $campuses[$campusKey]['buildings'][$buildingKey] = [
                    'building' => $building,
                    'label' => $building?->name ?? 'Tanpa Gedung',
                    'totals' => $this->emptyTotals(),
                    'room_ids' => [],
                    'floors' => [],
                ];
            }

            if (! isset($campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey])) {
                $campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey] = [
                    'floor' => $floor,
                    'label' => $floor?->name ?? 'Tanpa Lantai',
                    'totals' => $this->emptyTotals(),
                    'rooms' => [],
                ];
            }

            if (! isset($campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey]['rooms'][$roomKey])) {
                $campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey]['rooms'][$roomKey] = [
                    'room' => $room,
                    'label' => $room ? trim(($room->code ? $room->code.' — ' : '').$room->name) : 'Tanpa Ruangan',
                    'totals' => $this->emptyTotals(),
                    'by_category' => [],
                    'categories' => [],
                ];
            }

            $roomNode = &$campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey]['rooms'][$roomKey];

            if (! isset($roomNode['categories'][$categoryKey])) {
                $roomNode['categories'][$categoryKey] = [
                    'category' => $item->category,
                    'label' => $item->category?->name ?? 'Tanpa Kategori',
                    'totals' => $this->emptyTotals(),
                    'sub_categories' => [],
                ];
            }

            if (! isset($roomNode['categories'][$categoryKey]['sub_categories'][$subKey])) {
                $roomNode['categories'][$categoryKey]['sub_categories'][$subKey] = [
                    'sub_category' => $item->subCategory,
                    'label' => $item->subCategory?->name ?? 'Tanpa Sub Kategori',
                    'totals' => $this->emptyTotals(),
                    'items' => [],
                ];
            }

            $roomNode['categories'][$categoryKey]['sub_categories'][$subKey]['items'][] = $item;

            $price = 0.0;
            $this->bumpTotals($roomNode['categories'][$categoryKey]['sub_categories'][$subKey]['totals'], $price);
            $this->bumpTotals($roomNode['categories'][$categoryKey]['totals'], $price);
            $this->bumpTotals($roomNode['totals'], $price);
            $this->bumpTotals($campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey]['totals'], $price);
            $this->bumpTotals($campuses[$campusKey]['buildings'][$buildingKey]['totals'], $price);
            $this->bumpTotals($campuses[$campusKey]['totals'], $price);

            $catName = $item->category?->name ?? 'Lainnya';
            $roomNode['by_category'][$catName] = ($roomNode['by_category'][$catName] ?? 0) + 1;
            $campuses[$campusKey]['by_category'][$catName] = ($campuses[$campusKey]['by_category'][$catName] ?? 0) + 1;

            if ($room) {
                $campuses[$campusKey]['buildings'][$buildingKey]['room_ids'][$room->id] = true;
            }

            unset($roomNode);
        }

        return collect($campuses)
            ->map(function (array $campusNode) {
                $campusNode['buildings'] = collect($campusNode['buildings'])
                    ->map(function (array $buildingNode) {
                        $buildingNode['room_count'] = count($buildingNode['room_ids']);
                        unset($buildingNode['room_ids']);
                        $buildingNode['floors'] = collect($buildingNode['floors'])
                            ->map(function (array $floorNode) {
                                $floorNode['rooms'] = collect($floorNode['rooms'])
                                    ->map(function (array $roomNode) {
                                        $roomNode['categories'] = collect($roomNode['categories'])
                                            ->map(function (array $categoryNode) {
                                                $categoryNode['sub_categories'] = array_values($categoryNode['sub_categories']);

                                                return $categoryNode;
                                            })
                                            ->values()
                                            ->all();

                                        return $roomNode;
                                    })
                                    ->values()
                                    ->all();

                                return $floorNode;
                            })
                            ->values()
                            ->all();

                        return $buildingNode;
                    })
                    ->values()
                    ->all();

                ksort($campusNode['by_category']);

                return $campusNode;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, InventoryItem>  $items
     * @return array<string, mixed>
     */
    public function buildSummary(Collection $items): array
    {
        $byCategory = $items
            ->groupBy(fn (InventoryItem $i) => $i->category?->name ?: 'Tanpa Kategori')
            ->map->count()
            ->sortKeys()
            ->all();

        $byStatus = $items
            ->groupBy(fn (InventoryItem $i) => $i->status?->value ?: 'unknown')
            ->map->count()
            ->all();

        $byCondition = $items
            ->groupBy(fn (InventoryItem $i) => $i->condition?->value ?: 'unknown')
            ->map->count()
            ->all();

        $rusak = $items->filter(function (InventoryItem $i) {
            $status = $i->status;
            $condition = $i->condition;

            return in_array($status, [InventoryStatus::RusakRingan, InventoryStatus::RusakBerat], true)
                || in_array($condition, [InventoryCondition::RusakRingan, InventoryCondition::RusakBerat], true);
        })->count();

        return [
            'total_items' => $items->count(),
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'by_condition' => $byCondition,
            'aktif' => (int) ($byStatus[InventoryStatus::Aktif->value] ?? 0),
            'dipinjam' => (int) ($byStatus[InventoryStatus::Dipinjam->value] ?? 0),
            'maintenance' => (int) ($byStatus[InventoryStatus::Maintenance->value] ?? 0),
            'rusak' => $rusak,
            'category_named' => [
                'Furniture' => $this->countCategoryLike($byCategory, ['furniture', 'furnitur']),
                'Elektronik' => $this->countCategoryLike($byCategory, ['elektronik', 'electronic']),
                'ATK' => $this->countCategoryLike($byCategory, ['atk', 'alat tulis']),
                'Laboratorium' => $this->countCategoryLike($byCategory, ['laboratorium', 'lab']),
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function describeFilters(Request $request): array
    {
        $campus = $request->filled('campus_id')
            ? Campus::query()->find($request->integer('campus_id'))?->name
            : null;
        $building = $request->filled('building_id')
            ? Building::query()->find($request->integer('building_id'))?->name
            : null;
        $floor = $request->filled('floor_id')
            ? Floor::query()->find($request->integer('floor_id'))?->name
            : null;
        $room = $request->filled('room_id')
            ? Room::query()->find($request->integer('room_id'))?->name
            : null;
        $category = $request->filled('category_id')
            ? Category::query()->find($request->integer('category_id'))?->name
            : null;
        $sub = $request->filled('sub_category_id')
            ? SubCategory::query()->find($request->integer('sub_category_id'))?->name
            : null;

        $status = $request->input('status');
        $condition = $request->input('condition');

        return array_filter([
            'Kampus' => $campus,
            'Gedung' => $building,
            'Lantai' => $floor,
            'Ruangan' => $room,
            'Kategori' => $category,
            'Sub Kategori' => $sub,
            'Status' => $status ? (InventoryStatus::tryFrom($status)?->label() ?? $status) : null,
            'Kondisi' => $condition ? (InventoryCondition::tryFrom($condition)?->label() ?? $condition) : null,
            'Tahun Beli' => $request->input('purchase_year'),
            'Tgl Dari' => $request->input('purchase_date_from'),
            'Tgl Sampai' => $request->input('purchase_date_to'),
            'Pencarian' => $request->input('search'),
            'Sertakan Trash' => $request->boolean('include_trash') ? 'Ya' : null,
        ], fn ($v) => filled($v));
    }

    /**
     * @return array{count: int}
     */
    protected function emptyTotals(): array
    {
        return ['count' => 0];
    }

    /**
     * @param  array{count: int}  $totals
     */
    protected function bumpTotals(array &$totals, float $price = 0.0): void
    {
        $totals['count']++;
    }

    /**
     * @param  array<string, int>  $byCategory
     * @param  list<string>  $needles
     */
    protected function countCategoryLike(array $byCategory, array $needles): int
    {
        $total = 0;
        foreach ($byCategory as $name => $count) {
            $lower = mb_strtolower((string) $name);
            foreach ($needles as $needle) {
                if (str_contains($lower, $needle)) {
                    $total += (int) $count;
                    break;
                }
            }
        }

        return $total;
    }
}
