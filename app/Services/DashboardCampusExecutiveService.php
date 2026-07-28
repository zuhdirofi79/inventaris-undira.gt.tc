<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Models\Campus;
use App\Models\Category;
use App\Support\InventarisCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Executive campus inventory summary (Campus → Category → SubCategory → Status).
 * Isolated from DashboardStatsService::summary() so existing dashboard queries stay untouched.
 */
class DashboardCampusExecutiveService
{
    public const CACHE_KEY = 'dashboard-campus-summary';

    public const CACHE_TTL_SECONDS = 300;

    /**
     * @return array{
     *   generated_at: string,
     *   campuses: list<array<string, mixed>>,
     *   filter_options: array{campuses: list<array{id:int,name:string}>, categories: list<array{id:int,name:string}>}
     * }
     */
    public function summary(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            $this->forgetCache();
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->buildSummary();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *   generated_at: string,
     *   campuses: list<array<string, mixed>>,
     *   filter_options: array{campuses: list<array{id:int,name:string}>, categories: list<array{id:int,name:string}>}
     * }
     */
    protected function buildSummary(): array
    {
        $campuses = Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($campuses->isEmpty()) {
            return [
                'generated_at' => now()->toIso8601String(),
                'campuses' => [],
                'filter_options' => [
                    'campuses' => [],
                    'categories' => $categories->map(fn (Category $c) => [
                        'id' => (int) $c->id,
                        'name' => $c->name,
                    ])->values()->all(),
                ],
            ];
        }

        $aktif = InventoryStatus::Aktif->value;
        $dipinjam = InventoryStatus::Dipinjam->value;
        $maintenance = implode("','", [
            InventoryStatus::Maintenance->value,
            InventoryStatus::MenungguApproval->value,
            InventoryStatus::RevisiPerbaikan->value,
        ]);
        $rusak = implode("','", InventoryStatus::damagedValues());

        // Single aggregation query — no N+1.
        $rows = DB::table('inventory_items')
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->join('campuses', 'campuses.id', '=', 'buildings.campus_id')
            ->join('categories', 'categories.id', '=', 'inventory_items.category_id')
            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'inventory_items.sub_category_id')
            ->whereNull('inventory_items.deleted_at')
            ->whereNull('rooms.deleted_at')
            ->whereNull('floors.deleted_at')
            ->whereNull('buildings.deleted_at')
            ->where('campuses.is_active', true)
            ->whereIn('campuses.id', $campuses->pluck('id'))
            ->groupBy(
                'campuses.id',
                'campuses.name',
                'campuses.code',
                'categories.id',
                'categories.name',
                'sub_categories.id',
                DB::raw("COALESCE(sub_categories.name, 'Tanpa sub')"),
            )
            ->orderBy('campuses.name')
            ->orderBy('categories.name')
            ->orderBy('sub_categories.name')
            ->select([
                'campuses.id as campus_id',
                'campuses.name as campus_name',
                'campuses.code as campus_code',
                'categories.id as category_id',
                'categories.name as category_name',
                'sub_categories.id as sub_category_id',
                DB::raw("COALESCE(sub_categories.name, 'Tanpa sub') as sub_name"),
                DB::raw('COUNT(inventory_items.id) as total'),
                DB::raw("SUM(CASE WHEN inventory_items.status = '{$aktif}' THEN 1 ELSE 0 END) as aktif"),
                DB::raw("SUM(CASE WHEN inventory_items.status = '{$dipinjam}' THEN 1 ELSE 0 END) as dipinjam"),
                DB::raw("SUM(CASE WHEN inventory_items.status IN ('{$maintenance}') THEN 1 ELSE 0 END) as maintenance"),
                DB::raw("SUM(CASE WHEN inventory_items.status IN ('{$rusak}') THEN 1 ELSE 0 END) as rusak"),
            ])
            ->get();

        $byCampus = $rows->groupBy('campus_id');

        $campusPayload = $campuses->map(function (Campus $campus) use ($byCampus) {
            /** @var Collection<int, object> $campusRows */
            $campusRows = $byCampus->get($campus->id, collect());

            $categoriesPayload = $campusRows
                ->groupBy('category_id')
                ->map(function (Collection $catRows) use ($campus) {
                    $first = $catRows->first();
                    $catTotal = (int) $catRows->sum('total');
                    $catAktif = (int) $catRows->sum('aktif');
                    $catDipinjam = (int) $catRows->sum('dipinjam');
                    $catMaintenance = (int) $catRows->sum('maintenance');
                    $catRusak = (int) $catRows->sum('rusak');

                    $subs = $catRows->map(function ($row) use ($campus, $first) {
                        $subId = $row->sub_category_id ? (int) $row->sub_category_id : 0;
                        $categoryId = (int) $first->category_id;

                        return [
                            'sub_category_id' => $subId,
                            'name' => (string) $row->sub_name,
                            'aktif' => (int) $row->aktif,
                            'dipinjam' => (int) $row->dipinjam,
                            'maintenance' => (int) $row->maintenance,
                            'rusak' => (int) $row->rusak,
                            'total' => (int) $row->total,
                            'url' => route('inventory.category', $categoryId).'?'.http_build_query(array_filter([
                                'campus_id' => $campus->id,
                                'sub_category_id' => $subId > 0 ? $subId : null,
                            ])),
                        ];
                    })
                        ->sortByDesc('total')
                        ->values()
                        ->all();

                    return [
                        'category_id' => (int) $first->category_id,
                        'name' => (string) $first->category_name,
                        'total' => $catTotal,
                        'aktif' => $catAktif,
                        'dipinjam' => $catDipinjam,
                        'maintenance' => $catMaintenance,
                        'rusak' => $catRusak,
                        'subcategories' => $subs,
                        'subcategory_count' => count($subs),
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->all();

            $total = (int) collect($categoriesPayload)->sum('total');

            // Attach progress share per category relative to campus total.
            $categoriesPayload = array_map(function (array $cat) use ($total) {
                $cat['share_percent'] = $total > 0
                    ? (int) round(($cat['total'] / $total) * 100)
                    : 0;

                return $cat;
            }, $categoriesPayload);

            return [
                'campus_id' => (int) $campus->id,
                'campus_name' => $campus->name,
                'campus_code' => $campus->code,
                'total' => $total,
                'category_count' => count($categoriesPayload),
                'subcategory_count' => (int) collect($categoriesPayload)->sum('subcategory_count'),
                'aktif' => (int) collect($categoriesPayload)->sum('aktif'),
                'dipinjam' => (int) collect($categoriesPayload)->sum('dipinjam'),
                'maintenance' => (int) collect($categoriesPayload)->sum('maintenance'),
                'rusak' => (int) collect($categoriesPayload)->sum('rusak'),
                'categories' => $categoriesPayload,
            ];
        })->values()->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'campuses' => $campusPayload,
            'filter_options' => [
                'campuses' => $campuses->map(fn (Campus $c) => [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                ])->values()->all(),
                'categories' => $categories->map(fn (Category $c) => [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                ])->values()->all(),
            ],
        ];
    }
}
