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
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds hierarchical inventory reports from the same filtered InventoryItem
 * source used by Dashboard, Data Inventaris, and Room Detail.
 */
class InventoryReportService
{
    public const MAX_ROWS = 5000;

    public function __construct(
        protected InventoryFilterService $filterService,
    ) {}

    /**
     * @return array{
     *     items: Collection<int, InventoryItem>,
     *     tree: list<array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     filters: array<string, string>,
     *     meta: array<string, mixed>
     * }
     */
    public function build(Request $request, string $orientation = 'portrait'): array
    {
        $items = $this->fetchItems($request);
        $tree = $this->buildTree($items);
        $now = now();

        return [
            'items' => $items,
            'tree' => $tree,
            'summary' => $this->buildSummary($items),
            'filters' => $this->describeFilters($request),
            'meta' => [
                'printed_at' => $now->translatedFormat('d F Y H:i'),
                'printed_date' => $now->translatedFormat('d F Y'),
                'printed_time' => $now->format('H:i'),
                'user_name' => $request->user()?->name ?? 'Sistem',
                'title' => 'Laporan Inventaris Universitas Dian Nusantara',
                'report_title' => 'Laporan Inventaris',
                'organization' => 'Universitas Dian Nusantara',
                'system' => 'Sistem Inventaris',
                'orientation' => in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait',
            ],
        ];
    }

    /**
     * @return Collection<int, InventoryItem>
     */
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
                'pic:id,name',
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
                    'by_sub_category' => [],
                    'buildings' => [],
                ];
            }

            if (! isset($campuses[$campusKey]['buildings'][$buildingKey])) {
                $campuses[$campusKey]['buildings'][$buildingKey] = [
                    'building' => $building,
                    'label' => $building?->name ?? 'Tanpa Gedung',
                    'totals' => $this->emptyTotals(),
                    'by_sub_category' => [],
                    'room_ids' => [],
                    'floors' => [],
                ];
            }

            if (! isset($campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey])) {
                $campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey] = [
                    'floor' => $floor,
                    'label' => $floor?->name ?? 'Tanpa Lantai',
                    'totals' => $this->emptyTotals(),
                    'by_sub_category' => [],
                    'rooms' => [],
                ];
            }

            if (! isset($campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey]['rooms'][$roomKey])) {
                $campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey]['rooms'][$roomKey] = [
                    'room' => $room,
                    'label' => $room ? trim(($room->code ? $room->code.' — ' : '').$room->name) : 'Tanpa Ruangan',
                    'totals' => $this->emptyTotals(),
                    'by_category' => [],
                    'by_sub_category' => [],
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

            $this->bumpTotals($roomNode['categories'][$categoryKey]['sub_categories'][$subKey]['totals'], $item);
            $this->bumpTotals($roomNode['categories'][$categoryKey]['totals'], $item);
            $this->bumpTotals($roomNode['totals'], $item);
            $this->bumpTotals($campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey]['totals'], $item);
            $this->bumpTotals($campuses[$campusKey]['buildings'][$buildingKey]['totals'], $item);
            $this->bumpTotals($campuses[$campusKey]['totals'], $item);

            $catName = $item->category?->name ?? 'Lainnya';
            $roomNode['by_category'][$catName] = ($roomNode['by_category'][$catName] ?? 0) + 1;
            $campuses[$campusKey]['by_category'][$catName] = ($campuses[$campusKey]['by_category'][$catName] ?? 0) + 1;

            $subLabel = $item->subCategory?->name ?? 'Tanpa Sub Kategori';
            $roomNode['by_sub_category'][$subLabel] = ($roomNode['by_sub_category'][$subLabel] ?? 0) + 1;
            $campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey]['by_sub_category'][$subLabel]
                = ($campuses[$campusKey]['buildings'][$buildingKey]['floors'][$floorKey]['by_sub_category'][$subLabel] ?? 0) + 1;
            $campuses[$campusKey]['buildings'][$buildingKey]['by_sub_category'][$subLabel]
                = ($campuses[$campusKey]['buildings'][$buildingKey]['by_sub_category'][$subLabel] ?? 0) + 1;
            $campuses[$campusKey]['by_sub_category'][$subLabel]
                = ($campuses[$campusKey]['by_sub_category'][$subLabel] ?? 0) + 1;

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
                        $buildingNode['sub_category_recap'] = $this->finalizeSubCategoryRecap($buildingNode['by_sub_category'] ?? []);
                        unset($buildingNode['by_sub_category']);
                        $buildingNode['floors'] = collect($buildingNode['floors'])
                            ->map(function (array $floorNode) {
                                $floorNode['sub_category_recap'] = $this->finalizeSubCategoryRecap($floorNode['by_sub_category'] ?? []);
                                unset($floorNode['by_sub_category']);
                                $floorNode['rooms'] = collect($floorNode['rooms'])
                                    ->map(function (array $roomNode) {
                                        $roomNode['sub_category_recap'] = $this->finalizeSubCategoryRecap($roomNode['by_sub_category'] ?? []);
                                        unset($roomNode['by_sub_category']);
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
                $campusNode['sub_category_recap'] = $this->finalizeSubCategoryRecap($campusNode['by_sub_category'] ?? []);
                unset($campusNode['by_sub_category']);

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

        $rusak = $items->filter(fn (InventoryItem $i) => $this->isDamaged($i))->count();

        $maintenanceStatuses = [
            InventoryStatus::Maintenance->value,
            InventoryStatus::MenungguApproval->value,
            InventoryStatus::RevisiPerbaikan->value,
        ];
        $maintenance = $items->filter(
            fn (InventoryItem $i) => in_array($i->status?->value, $maintenanceStatuses, true)
        )->count();

        $subCategoryRecap = $this->buildSubCategoryRecap($items);

        return [
            'total_items' => $items->count(),
            'campus_count' => $items->map(fn (InventoryItem $i) => $i->room?->floor?->building?->campus_id)->filter()->unique()->count(),
            'building_count' => $items->map(fn (InventoryItem $i) => $i->room?->floor?->building_id)->filter()->unique()->count(),
            'floor_count' => $items->map(fn (InventoryItem $i) => $i->room?->floor_id)->filter()->unique()->count(),
            'room_count' => $items->map(fn (InventoryItem $i) => $i->room_id)->filter()->unique()->count(),
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'by_condition' => $byCondition,
            'sub_category_recap' => $subCategoryRecap,
            'composition' => $this->buildComposition($items, $subCategoryRecap),
            'aktif' => (int) ($byStatus[InventoryStatus::Aktif->value] ?? 0),
            'dipinjam' => (int) ($byStatus[InventoryStatus::Dipinjam->value] ?? 0),
            'maintenance' => $maintenance,
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
     * Executive composition block for print/PDF/Excel (filter-aware).
     *
     * @param  Collection<int, InventoryItem>  $items
     * @param  array{rows: list<array{label: string, count: int}>, kind_count: int, total_units: int}  $subCategoryRecap
     * @return array<string, mixed>
     */
    public function buildComposition(Collection $items, ?array $subCategoryRecap = null): array
    {
        $total = $items->count();
        $subCategoryRecap ??= $this->buildSubCategoryRecap($items);

        $categoryCounts = [];
        foreach ($items as $item) {
            $label = $item->category?->name ?? 'Tanpa Kategori';
            $categoryCounts[$label] = ($categoryCounts[$label] ?? 0) + 1;
        }
        arsort($categoryCounts, SORT_NUMERIC);

        $categoryRows = [];
        foreach ($categoryCounts as $label => $count) {
            $pct = $total > 0 ? round(($count / $total) * 100) : 0;
            $categoryRows[] = [
                'label' => (string) $label,
                'count' => (int) $count,
                'percent' => (int) $pct,
            ];
        }

        // Ensure percentages sum to 100 when there is data (adjust largest row).
        if ($total > 0 && $categoryRows !== []) {
            $sumPct = array_sum(array_column($categoryRows, 'percent'));
            $diff = 100 - $sumPct;
            if ($diff !== 0) {
                $categoryRows[0]['percent'] = max(0, $categoryRows[0]['percent'] + $diff);
            }
        }

        $conditionBuckets = [
            'Bagus' => 0,
            'Maintenance' => 0,
            'Rusak' => 0,
            'Non Aktif' => 0,
        ];
        $statusBuckets = [
            'Aktif' => 0,
            'Dipinjam' => 0,
            'Maintenance' => 0,
            'Rusak' => 0,
            'Non Aktif' => 0,
        ];

        foreach ($items as $item) {
            $conditionBuckets[$this->compositionConditionBucket($item)]++;
            $statusBuckets[$this->compositionStatusBucket($item)]++;
        }

        $categoryKindCount = count($categoryRows);
        $subKindCount = (int) ($subCategoryRecap['kind_count'] ?? 0);

        return [
            'by_category' => [
                'rows' => $categoryRows,
                'total_units' => $total,
                'kind_count' => $categoryKindCount,
            ],
            'by_sub_category' => $subCategoryRecap,
            'top_sub_categories' => array_slice($subCategoryRecap['rows'] ?? [], 0, 10),
            'by_condition' => [
                'rows' => $this->bucketRows($conditionBuckets),
                'total_units' => $total,
            ],
            'by_status' => [
                'rows' => $this->bucketRows($statusBuckets),
                'total_units' => $total,
            ],
            'location' => [
                'kampus' => $items->map(fn (InventoryItem $i) => $i->room?->floor?->building?->campus_id)->filter()->unique()->count(),
                'gedung' => $items->map(fn (InventoryItem $i) => $i->room?->floor?->building_id)->filter()->unique()->count(),
                'lantai' => $items->map(fn (InventoryItem $i) => $i->room?->floor_id)->filter()->unique()->count(),
                'ruangan' => $items->map(fn (InventoryItem $i) => $i->room_id)->filter()->unique()->count(),
                'kategori' => $categoryKindCount,
                'sub_kategori' => $subKindCount,
                'total_inventaris' => $total,
            ],
        ];
    }

    /**
     * @param  array<string, int>  $buckets
     * @return list<array{label: string, count: int}>
     */
    protected function bucketRows(array $buckets): array
    {
        $rows = [];
        foreach ($buckets as $label => $count) {
            $rows[] = ['label' => $label, 'count' => (int) $count];
        }

        return $rows;
    }

    protected function compositionConditionBucket(InventoryItem $item): string
    {
        $status = $item->status;

        if ($status instanceof InventoryStatus && $status->isTerminal()) {
            return 'Non Aktif';
        }

        if ($this->isDamaged($item)) {
            return 'Rusak';
        }

        if ($status instanceof InventoryStatus && in_array($status, [
            InventoryStatus::Maintenance,
            InventoryStatus::MenungguApproval,
            InventoryStatus::RevisiPerbaikan,
        ], true)) {
            return 'Maintenance';
        }

        return 'Bagus';
    }

    protected function compositionStatusBucket(InventoryItem $item): string
    {
        $status = $item->status;

        if ($status === InventoryStatus::Aktif) {
            return 'Aktif';
        }
        if ($status === InventoryStatus::Dipinjam) {
            return 'Dipinjam';
        }
        if ($status instanceof InventoryStatus && in_array($status, [
            InventoryStatus::Maintenance,
            InventoryStatus::MenungguApproval,
            InventoryStatus::RevisiPerbaikan,
        ], true)) {
            return 'Maintenance';
        }
        if ($status instanceof InventoryStatus && ($status->isDamagedList() || $status === InventoryStatus::Hilang)) {
            return 'Rusak';
        }
        if ($status instanceof InventoryStatus && $status->isTerminal()) {
            return 'Non Aktif';
        }

        return 'Non Aktif';
    }

    /**
     * Sub-category counts for the active filter set, sorted by quantity desc.
     *
     * @param  Collection<int, InventoryItem>  $items
     * @return array{rows: list<array{label: string, count: int}>, kind_count: int, total_units: int}
     */
    public function buildSubCategoryRecap(Collection $items): array
    {
        $counts = [];
        foreach ($items as $item) {
            $label = $item->subCategory?->name ?? 'Tanpa Sub Kategori';
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return $this->finalizeSubCategoryRecap($counts);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{rows: list<array{label: string, count: int}>, kind_count: int, total_units: int}
     */
    public function finalizeSubCategoryRecap(array $counts): array
    {
        arsort($counts, SORT_NUMERIC);

        $rows = [];
        foreach ($counts as $label => $count) {
            $rows[] = [
                'label' => (string) $label,
                'count' => (int) $count,
            ];
        }

        return [
            'rows' => $rows,
            'kind_count' => count($rows),
            'total_units' => (int) array_sum($counts),
        ];
    }

    /**
     * Human-readable filters for report header (empty → "Semua").
     *
     * @return array<string, string>
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
        $pic = $request->filled('pic_id')
            ? User::query()->find($request->integer('pic_id'))?->name
            : null;

        $status = $request->input('status');
        $condition = $request->input('condition');

        $filters = [
            'Kampus' => $campus ?: 'Semua',
            'Gedung' => $building ?: 'Semua',
            'Lantai' => $floor ?: 'Semua',
            'Ruangan' => $room ?: 'Semua',
            'Kategori' => $category ?: 'Semua',
            'Sub Kategori' => $sub ?: 'Semua',
            'Status' => $status ? (InventoryStatus::tryFrom((string) $status)?->label() ?? (string) $status) : 'Semua',
            'Kondisi' => $condition ? (InventoryCondition::tryFrom((string) $condition)?->label() ?? (string) $condition) : 'Semua',
        ];

        if ($pic) {
            $filters['PIC'] = $pic;
        }
        if ($request->filled('purchase_year')) {
            $filters['Tahun Beli'] = (string) $request->input('purchase_year');
        }
        if ($request->filled('purchase_date_from')) {
            $filters['Tgl Dari'] = (string) $request->input('purchase_date_from');
        }
        if ($request->filled('purchase_date_to')) {
            $filters['Tgl Sampai'] = (string) $request->input('purchase_date_to');
        }
        if ($request->filled('search')) {
            $filters['Pencarian'] = (string) $request->input('search');
        }
        if ($request->boolean('include_trash')) {
            $filters['Sertakan Trash'] = 'Ya';
        }

        return $filters;
    }

    /**
     * @return array{count: int, aktif: int, rusak: int}
     */
    protected function emptyTotals(): array
    {
        return ['count' => 0, 'aktif' => 0, 'rusak' => 0];
    }

    /**
     * @param  array{count: int, aktif: int, rusak: int}  $totals
     */
    protected function bumpTotals(array &$totals, InventoryItem $item): void
    {
        $totals['count']++;

        if ($item->status === InventoryStatus::Aktif) {
            $totals['aktif']++;
        }

        if ($this->isDamaged($item)) {
            $totals['rusak']++;
        }
    }

    protected function isDamaged(InventoryItem $item): bool
    {
        $status = $item->status;
        $condition = $item->condition;

        return ($status instanceof InventoryStatus && $status->isDamagedList())
            || in_array($condition, [InventoryCondition::RusakRingan, InventoryCondition::RusakBerat], true);
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
