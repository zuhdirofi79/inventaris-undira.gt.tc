<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\InventoryStatus;
use App\Http\Requests\BulkRelocateInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogService;
use App\Services\DashboardStatsService;
use App\Services\DashboardTreeService;
use App\Services\InventoryCodeService;
use App\Services\InventoryFilterService;
use App\Services\InventorySearchService;
use App\Services\LocationHierarchyService;
use App\Services\TrashCascadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected InventoryCodeService $codeService,
        protected InventoryFilterService $filterService,
        protected InventorySearchService $inventorySearchService,
        protected LocationHierarchyService $locationHierarchy,
        protected TrashCascadeService $trash,
        protected DashboardStatsService $dashboardStats,
    ) {}

    /**
     * Campus-first inventory overview (client workflow entry point).
     */
    public function index(Request $request): View
    {
        $campuses = Campus::query()
            ->where('is_active', true)
            ->withCount([
                'buildings as buildings_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'slug', 'is_active']);

        $campusStats = collect($this->dashboardStats->campusStats())->keyBy('id');

        $stats = [];
        foreach ($campuses as $campus) {
            $row = $campusStats->get($campus->id, []);
            $stats[$campus->id] = [
                'total_units' => (int) ($row['total_units'] ?? 0),
                'total_items' => (int) ($row['total_inventory'] ?? 0),
                'floors' => (int) ($row['floors'] ?? 0),
                'rooms' => (int) ($row['rooms'] ?? 0),
                'available' => (int) ($row['available'] ?? 0),
                'borrowed' => (int) ($row['borrowed'] ?? 0),
                'maintenance' => (int) ($row['maintenance'] ?? 0),
                'damaged' => (int) ($row['damaged'] ?? 0),
            ];
        }

        return view('inventory.index', [
            'campuses' => $campuses,
            'stats' => $stats,
        ]);
    }

    /**
     * AJAX Select2 lookup — avoids loading all inventory into ops create forms.
     */
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $filters = [];
        if ($request->filled('status')) {
            $filters['status'] = (string) $request->input('status');
        }

        // Backward-compatible adapter — prefer InventorySelector for new forms.
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = collect($this->inventorySearchService->search($q, $filters, 30))
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'text' => ($row['inventory_number'] ?? '').' - '.($row['name'] ?? ''),
                'status' => $row['status'] ?? null,
            ])
            ->all();

        return response()->json(['results' => $results]);
    }

    /**
     * AJAX Select2 lookup for destination rooms (mutations).
     */
    public function lookupRooms(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $query = Room::query()
            ->where('is_active', true)
            ->with(['floor.building.campus'])
            ->select(['id', 'floor_id', 'code', 'name']);

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhereHas('floor.building.campus', function ($loc) use ($q) {
                        $loc->where('name', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%");
                    });
            });
        }

        $rooms = $query->orderBy('name')->limit(30)->get();

        return response()->json([
            'results' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'text' => $room->full_location,
            ]),
        ]);
    }

    /**
     * Category-first inventory overview (alternate browse mode).
     */
    public function categories(Request $request): View
    {
        $query = Category::query()->where('is_active', true);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        $this->applyLocationCategoryFilter($query, $request);

        $sort = $request->input('sort', 'name');
        $dir = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        if (! in_array($sort, ['name', 'code'], true)) {
            $sort = 'name';
        }
        $query->orderBy($sort, $dir);

        $categories = $query->paginate(12)->withQueryString();

        $stats = $this->categorySummaries($categories->pluck('id')->all(), $request);

        return view('inventory.categories', [
            'categories' => $categories,
            'stats' => $stats,
            'locationData' => $this->locationTree(),
            'filters' => $request->only(['search', 'campus_id', 'building_id', 'floor_id', 'room_id', 'sort', 'dir']),
        ]);
    }

    /**
     * Hierarchical browse for one campus: Building → Floor → Room → Units (lazy nested accordion).
     */
    public function byCampus(Request $request, Campus $campus): View
    {
        $baseQuery = $this->campusFilteredQuery($request, $campus);

        $buildingCounts = (clone $baseQuery)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->whereNull('rooms.deleted_at')
            ->whereNull('floors.deleted_at')
            ->groupBy('floors.building_id')
            ->selectRaw('floors.building_id as building_id, COUNT(inventory_items.id) as unit_count')
            ->pluck('unit_count', 'building_id');

        $buildingsQuery = Building::query()
            ->where('campus_id', $campus->id)
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('building_id')) {
            $buildingsQuery->where('id', (int) $request->input('building_id'));
        }

        $buildings = $buildingsQuery->get(['id', 'code', 'name'])
            ->map(function (Building $building) use ($buildingCounts) {
                return [
                    'id' => $building->id,
                    'code' => $building->code,
                    'name' => $building->name,
                    'unit_count' => (int) ($buildingCounts[$building->id] ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        $summary = $this->campusBrowseSummary($baseQuery);

        return view('inventory.campus', [
            'campus' => $campus,
            'buildings' => $buildings,
            'summary' => $summary,
            'locationData' => $this->locationTree($campus->id),
            'filters' => $request->only(['search', 'building_id', 'floor_id', 'room_id']),
            'treeUrls' => [
                'floors' => url('/inventory/campuses/'.$campus->id.'/buildings'),
                'rooms' => url('/inventory/campuses/'.$campus->id.'/floors'),
                'categories' => url('/inventory/campuses/'.$campus->id.'/rooms'),
            ],
        ]);
    }

    /**
     * Lazy: floors inside a building (JSON).
     */
    public function campusFloors(Request $request, Campus $campus, Building $building): JsonResponse
    {
        abort_unless($building->campus_id === $campus->id, 404);

        $baseQuery = $this->campusFilteredQuery($request, $campus)
            ->forBuilding($building->id);

        $floorCounts = (clone $baseQuery)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->whereNull('rooms.deleted_at')
            ->groupBy('rooms.floor_id')
            ->selectRaw('rooms.floor_id as floor_id, COUNT(inventory_items.id) as unit_count')
            ->pluck('unit_count', 'floor_id');

        $floorsQuery = Floor::query()
            ->where('building_id', $building->id)
            ->where('is_active', true)
            ->orderBy('floor_number')
            ->orderBy('name');

        if ($request->filled('floor_id')) {
            $floorsQuery->where('id', (int) $request->input('floor_id'));
        }

        $floors = $floorsQuery->get(['id', 'code', 'name', 'floor_number'])
            ->map(fn (Floor $floor) => [
                'id' => $floor->id,
                'code' => $floor->code,
                'name' => $floor->name,
                'unit_count' => (int) ($floorCounts[$floor->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        return response()->json(['nodes' => $floors]);
    }

    /**
     * Lazy: rooms inside a floor (JSON).
     */
    public function campusRooms(Request $request, Campus $campus, Floor $floor): JsonResponse
    {
        $floor->loadMissing('building:id,campus_id');
        abort_unless((int) $floor->building?->campus_id === (int) $campus->id, 404);

        $baseQuery = $this->campusFilteredQuery($request, $campus)
            ->forFloor($floor->id);

        $roomCounts = (clone $baseQuery)
            ->groupBy('room_id')
            ->selectRaw('room_id, COUNT(*) as unit_count')
            ->pluck('unit_count', 'room_id');

        $roomsQuery = Room::query()
            ->where('floor_id', $floor->id)
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('room_id')) {
            $roomsQuery->where('id', (int) $request->input('room_id'));
        }

        $rooms = $roomsQuery->get(['id', 'code', 'name'])
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'unit_count' => (int) ($roomCounts[$room->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        return response()->json(['nodes' => $rooms]);
    }

    /**
     * Lazy: categories inside a room (JSON counts only — no inventory rows).
     */
    public function campusRoomCategories(Request $request, Campus $campus, Room $room): JsonResponse
    {
        $this->assertRoomBelongsToCampus($campus, $room);

        $baseQuery = $this->campusFilteredQuery($request, $campus)
            ->inRoom($room->id);

        $categoryCounts = (clone $baseQuery)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, COUNT(*) as unit_count')
            ->pluck('unit_count', 'category_id');

        $categories = Category::query()
            ->whereIn('id', $categoryCounts->keys())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'unit_count' => (int) ($categoryCounts[$category->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        return response()->json(['nodes' => $categories]);
    }

    /**
     * Lazy: sub-categories inside room+category (JSON counts only).
     */
    public function campusRoomSubCategories(Request $request, Campus $campus, Room $room, Category $category): JsonResponse
    {
        $this->assertRoomBelongsToCampus($campus, $room);

        $baseQuery = $this->campusFilteredQuery($request, $campus)
            ->inRoom($room->id)
            ->where('category_id', $category->id);

        $subCounts = (clone $baseQuery)
            ->whereNotNull('sub_category_id')
            ->groupBy('sub_category_id')
            ->selectRaw('sub_category_id, COUNT(*) as unit_count')
            ->pluck('unit_count', 'sub_category_id');

        $nodes = SubCategory::query()
            ->where('category_id', $category->id)
            ->whereIn('id', $subCounts->keys())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (SubCategory $sub) => [
                'id' => $sub->id,
                'code' => $sub->code,
                'name' => $sub->name,
                'unit_count' => (int) ($subCounts[$sub->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        $uncategorized = (clone $baseQuery)
            ->where(function ($q) {
                $q->whereNull('sub_category_id')->orWhere('sub_category_id', 0);
            })
            ->count();

        if ($uncategorized > 0) {
            $nodes->push([
                'id' => 0,
                'code' => null,
                'name' => 'Tanpa Sub Kategori',
                'unit_count' => $uncategorized,
            ]);
        }

        return response()->json(['nodes' => $nodes]);
    }

    /**
     * Lazy: inventory table HTML for room + category + sub-category.
     * Use subCategory=0 for items without sub-category.
     */
    public function campusSubCategoryItems(
        Request $request,
        Campus $campus,
        Room $room,
        Category $category,
        int $subCategory,
    ): JsonResponse {
        $this->assertRoomBelongsToCampus($campus, $room);

        $query = $this->campusFilteredQuery($request, $campus)
            ->inRoom($room->id)
            ->where('category_id', $category->id);

        if ($subCategory > 0) {
            $sub = SubCategory::query()->findOrFail($subCategory);
            abort_unless((int) $sub->category_id === (int) $category->id, 404);
            $query->where('sub_category_id', $sub->id);
        } else {
            $query->where(function ($q) {
                $q->whereNull('sub_category_id')->orWhere('sub_category_id', 0);
            });
        }

        $items = $query
            ->select([
                'id', 'name', 'inventory_number', 'serial_number', 'group_key', 'unit_sequence',
                'status', 'condition', 'room_id', 'category_id', 'sub_category_id', 'unit',
            ])
            ->orderBy('name')
            ->orderBy('unit_sequence')
            ->limit(500)
            ->get();

        $groups = [];
        foreach ($items as $item) {
            $groupKey = $item->group_key ?: 'item-'.$item->id;
            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'name' => $item->name,
                    'group_key' => $item->group_key,
                    'unit' => $item->unit,
                    'items' => [],
                ];
            }
            $groups[$groupKey]['items'][] = $item;
        }

        $html = view('inventory._unit-table', ['groups' => $groups])->render();

        return response()->json([
            'html' => $html,
            'unit_count' => $items->count(),
        ]);
    }

    protected function assertRoomBelongsToCampus(Campus $campus, Room $room): void
    {
        $room->loadMissing('floor.building:id,campus_id');
        abort_unless((int) $room->floor?->building?->campus_id === (int) $campus->id, 404);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\InventoryItem>
     */
    protected function campusFilteredQuery(Request $request, Campus $campus)
    {
        return $this->filterService->apply(
            $request,
            InventoryItem::query()->forCampus($campus->id),
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\InventoryItem>  $baseQuery
     * @return array{total_units:int,total_groups:int,buildings:int,floors:int,rooms:int}
     */
    protected function campusBrowseSummary($baseQuery): array
    {
        $totalUnits = (clone $baseQuery)->count();

        $driver = Schema::getConnection()->getDriverName();
        $groupExpr = $driver === 'sqlite'
            ? "COUNT(DISTINCT COALESCE(NULLIF(group_key, ''), 'item-' || inventory_items.id))"
            : "COUNT(DISTINCT COALESCE(NULLIF(group_key, ''), CONCAT('item-', inventory_items.id)))";

        $totalGroups = (int) (clone $baseQuery)
            ->toBase()
            ->selectRaw("{$groupExpr} as aggregate")
            ->value('aggregate');

        $location = (clone $baseQuery)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->whereNull('rooms.deleted_at')
            ->whereNull('floors.deleted_at')
            ->toBase()
            ->selectRaw('COUNT(DISTINCT floors.building_id) as buildings')
            ->selectRaw('COUNT(DISTINCT floors.id) as floors')
            ->selectRaw('COUNT(DISTINCT rooms.id) as rooms')
            ->first();

        return [
            'total_units' => $totalUnits,
            'total_groups' => $totalGroups,
            'buildings' => (int) ($location->buildings ?? 0),
            'floors' => (int) ($location->floors ?? 0),
            'rooms' => (int) ($location->rooms ?? 0),
        ];
    }

    /**
     * Hierarchical browse: Category → Campus → Building → Floor → Room → Items.
     */
    public function byCategory(Request $request, Category $category): View
    {
        $itemsQuery = InventoryItem::query()
            ->where('category_id', $category->id)
            ->select([
                'id', 'name', 'inventory_number', 'group_key', 'unit_sequence',
                'status', 'condition', 'room_id', 'category_id', 'sub_category_id',
            ])
            ->with([
                'subCategory:id,category_id,code,name',
                'room:id,floor_id,code,name',
                'room.floor:id,building_id,code,name,floor_number',
                'room.floor.building:id,campus_id,code,name',
                'room.floor.building.campus:id,code,name',
            ]);

        $items = $this->filterService
            ->apply($request, $itemsQuery)
            ->orderBy('name')
            ->orderBy('unit_sequence')
            ->limit(500)
            ->get();

        $tree = $this->buildLocationTree($items);

        $totalMatching = InventoryItem::query()->where('category_id', $category->id);
        $totalMatching = $this->filterService->apply($request, $totalMatching)->count();

        $summary = [
            'total_units' => $items->count(),
            'total_matching' => $totalMatching,
            'truncated' => $totalMatching > $items->count(),
            'total_groups' => $items->groupBy(fn ($i) => $i->group_key ?: $i->name)->count(),
            'campuses' => $items->pluck('room.floor.building.campus.id')->unique()->filter()->count(),
            'buildings' => $items->pluck('room.floor.building.id')->unique()->filter()->count(),
            'rooms' => $items->pluck('room_id')->unique()->filter()->count(),
        ];

        return view('inventory.category', [
            'category' => $category,
            'tree' => $tree,
            'summary' => $summary,
            'locationData' => $this->locationTree(),
            'filters' => $request->only(['search', 'campus_id', 'building_id', 'floor_id', 'room_id']),
        ]);
    }

    /**
     * Flat item list (DataTables) — kept for search/export power users.
     */
    public function items(Request $request): View|JsonResponse
    {
        // DataTables always sends `draw`; do not rely only on X-Requested-With.
        if ($request->ajax() || $request->wantsJson() || $request->filled('draw')) {
            return $this->datatable($request);
        }

        return view('inventory.items', [
            'locationData' => $this->locationTree(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'orgUnits' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $params = ['tab' => 'konfigurasi', 'create' => 1];

        if ($request->filled('room_id')) {
            $room = Room::query()->with('floor.building.campus')->find($request->integer('room_id'));
            if ($room) {
                $params['room_id'] = $room->id;
                $params['floor_id'] = $room->floor_id;
                $params['building_id'] = $room->floor?->building_id;
                $params['campus_id'] = $room->floor?->building?->campus_id;
            }
        } else {
            foreach (['campus_id', 'building_id', 'floor_id', 'room_id'] as $key) {
                if ($request->filled($key)) {
                    $params[$key] = $request->integer($key);
                }
            }
        }

        return redirect()->route('categories.index', array_filter($params));
    }

    public function store(Request $request): RedirectResponse
    {
        // Single entry-point: physical assets are created only via Konfigurasi Inventaris.
        return redirect()
            ->route('categories.index', ['tab' => 'konfigurasi', 'create' => 1])
            ->with('error', 'Inventaris fisik hanya dapat dibuat melalui menu Konfigurasi Inventaris.');
    }

    public function show(InventoryItem $inventory): View
    {
        $this->codeService->ensureCodes($inventory);

        $inventory->load([
            'category', 'subCategory', 'room.floor.building.campus',
            'orgUnit', 'procurementType', 'pic',
            'supplier', 'vendor', 'creator', 'updater',
            'photos' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id'),
            'attachments' => fn ($q) => $q->latest()->limit(50),
            'mutations' => fn ($q) => $q->with(['fromRoom', 'toRoom', 'movedBy'])->latest()->limit(25),
            'borrowings' => fn ($q) => $q->with('approvedBy')->latest()->limit(25),
            'maintenances' => fn ($q) => $q->with(['vendor', 'creator', 'photos'])->latest('service_date')->latest('id')->limit(50),
            'maintenanceSchedules' => fn ($q) => $q->latest('next_maintenance_date')->limit(20),
        ]);

        $timeline = app(\App\Services\InventoryHistoryService::class)->timeline($inventory);

        return view('inventory.show', compact('inventory', 'timeline'));
    }

    public function edit(InventoryItem $inventory): View
    {
        return view('inventory.edit', [
            'inventory' => $inventory->load(['category', 'subCategory', 'room.floor.building.campus', 'pic']),
            ...$this->formLookups($inventory),
        ]);
    }

    public function update(UpdateInventoryRequest $request, InventoryItem $inventory): RedirectResponse
    {
        $oldValues = $inventory->loadMissing('pic')->toArray();
        $oldValues['pic_name'] = $inventory->pic?->name;
        $data = $this->syncProcurementFunding($request->validated());
        unset($data['quantity']);

        if (array_key_exists('pic_id', $data)) {
            $picId = $data['pic_id'] ?: null;
            $data['pic_id'] = $picId;
            $data['responsible_person'] = $picId
                ? User::query()->whereKey($picId)->value('name')
                : null;
        }

        $inventory->update([
            ...$data,
            'quantity' => 1,
            'updated_by' => $request->user()->id,
        ]);

        $fresh = $inventory->fresh(['pic']);
        $newValues = $fresh->toArray();
        $newValues['pic_name'] = $fresh->pic?->name;

        $this->auditLogService->log($request->user(), AuditAction::Update, $inventory, $oldValues, $newValues);

        DashboardTreeService::flushCache();

        return redirect()->route('inventory.show', $inventory)->with('success', 'Inventaris berhasil diperbarui.');
    }

    public function destroy(Request $request, InventoryItem $inventory): RedirectResponse
    {
        $categoryId = $inventory->category_id;
        $this->trash->softDelete($inventory, $request->user());

        return redirect()
            ->route('inventory.category', $categoryId)
            ->with('success', 'Inventaris dipindahkan ke Trash.');
    }

    public function trash(): RedirectResponse
    {
        return redirect()->route('trash.index', ['tab' => 'inventaris']);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $inventory = InventoryItem::onlyTrashed()->findOrFail($id);
        $this->trash->restore($inventory, $request->user());

        return redirect()
            ->route('trash.index', ['tab' => 'inventaris'])
            ->with('success', 'Inventaris berhasil dipulihkan.');
    }

    public function groupUnits(string $groupKey): View
    {
        $items = InventoryItem::query()
            ->where('group_key', $groupKey)
            ->with(['room.floor.building.campus', 'category', 'subCategory'])
            ->orderBy('unit_sequence')
            ->get();

        return view('inventory.group-units', compact('items', 'groupKey'));
    }

    public function bulkRelocate(BulkRelocateInventoryRequest $request): RedirectResponse
    {
        $toRoom = Room::query()->findOrFail($request->input('to_room_id'));
        $ids = $request->input('inventory_ids', []);

        $moved = DB::transaction(function () use ($request, $toRoom, $ids) {
            $items = InventoryItem::query()->whereIn('id', $ids)->get();
            $count = 0;

            foreach ($items as $item) {
                $fromRoomId = $item->room_id;

                InventoryMutation::query()->create([
                    'inventory_item_id' => $item->id,
                    'from_room_id' => $fromRoomId,
                    'to_room_id' => $toRoom->id,
                    'moved_by' => $request->user()->id,
                    'reason' => $request->input('reason'),
                    'notes' => $request->input('notes'),
                ]);

                $item->update([
                    'room_id' => $toRoom->id,
                    'updated_by' => $request->user()->id,
                ]);
                $count++;
            }

            return $count;
        });

        $this->auditLogService->log(
            $request->user(),
            AuditAction::Update,
            null,
            null,
            [
                'to_room_id' => $toRoom->id,
                'inventory_ids' => $ids,
                'affected_count' => $moved,
                'reason' => $request->input('reason'),
            ],
            'INVENTORY_BULK_RELOCATED',
            $request->input('reason'),
            $moved,
        );

        return back()->with('success', 'Inventaris berhasil dipindahkan.');
    }

    protected function datatable(Request $request): JsonResponse
    {
        try {
            $query = $this->filterService->apply($request)
                ->with(['category', 'subCategory', 'room.floor.building.campus', 'pic']);

            $recordsTotal = InventoryItem::count();
            $recordsFiltered = (clone $query)->count();

            $orderColumn = $request->input('order.0.column', 0);
            $orderDir = $request->input('order.0.dir', 'asc');
            $columns = [null, 'inventory_number', 'name', 'category_id', null, null, 'status', 'condition', null];
            $orderBy = $columns[$orderColumn] ?? 'inventory_number';

            $items = $query
                ->when($orderBy, fn ($q) => $q->orderBy($orderBy, $orderDir), fn ($q) => $q->orderBy('inventory_number', 'asc'))
                ->skip($request->input('start', 0))
                ->take($request->input('length', 10))
                ->get()
                ->map(function (InventoryItem $item) {
                    $data = $item->toArray();
                    $data['status'] = [
                        'value' => $item->status?->value,
                        'label' => $item->status?->label(),
                    ];
                    $data['condition'] = [
                        'value' => $item->condition?->value,
                        'label' => $item->condition?->label(),
                    ];
                    $data['pic_name'] = $item->picDisplayName();

                    return $data;
                });

            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $items,
            ]);
        } catch (\Throwable $e) {
            Log::error('Inventory DataTables failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'draw' => $request->input('draw'),
            ]);

            // Valid JSON (not HTML) so DataTables does not show a generic Ajax error dialog.
            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data inventaris. Silakan hubungi Administrator.',
            ], 500);
        }
    }

    /**
     * Cascade dropdown data — always a Collection (empty when campus/location missing).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Campus>
     */
    protected function locationTree(?int $campusId = null)
    {
        $tree = $this->locationHierarchy->activeTree($campusId);

        return $tree instanceof \Illuminate\Support\Collection ? $tree : collect();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formLookups(?InventoryItem $inventory = null): array
    {
        $users = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        if ($inventory?->pic_id && ! $users->contains('id', $inventory->pic_id)) {
            $currentPic = User::query()->whereKey($inventory->pic_id)->first(['id', 'name']);
            if ($currentPic) {
                $users = $users->prepend($currentPic)->unique('id')->values();
            }
        }

        return [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'locationData' => $this->locationTree(),
            'orgUnits' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'procurementTypes' => ProcurementType::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'users' => $users,
        ];
    }

    /**
     * Keep legacy funding_source text in sync when procurement type is chosen.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function syncProcurementFunding(array $data): array
    {
        if (! empty($data['procurement_type_id'])) {
            $type = ProcurementType::query()->find($data['procurement_type_id']);
            if ($type) {
                $data['funding_source'] = $type->name;
            }
        }

        return $data;
    }

    protected function applyLocationCategoryFilter($query, Request $request): void
    {
        if ($request->filled('room_id')) {
            $query->whereHas('inventoryItems', fn ($q) => $q->where('room_id', $request->input('room_id')));

            return;
        }

        if ($request->filled('floor_id')) {
            $query->whereHas('inventoryItems.room', fn ($q) => $q->where('floor_id', $request->input('floor_id')));

            return;
        }

        if ($request->filled('building_id')) {
            $query->whereHas('inventoryItems.room.floor', fn ($q) => $q->where('building_id', $request->input('building_id')));

            return;
        }

        if ($request->filled('campus_id')) {
            $query->whereHas('inventoryItems.room.floor.building', fn ($q) => $q->where('campus_id', $request->input('campus_id')));
        }
    }

    /**
     * @param  list<int|string>  $categoryIds
     * @return array<int|string, array<string, int>>
     */
    protected function categorySummaries(array $categoryIds, Request $request): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $base = InventoryItem::query()
            ->whereIn('category_id', $categoryIds)
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->input('room_id')))
            ->when($request->filled('floor_id') && ! $request->filled('room_id'), fn ($q) => $q->forFloor((int) $request->input('floor_id')))
            ->when($request->filled('building_id') && ! $request->filled('floor_id') && ! $request->filled('room_id'), fn ($q) => $q->forBuilding((int) $request->input('building_id')))
            ->when($request->filled('campus_id') && ! $request->filled('building_id') && ! $request->filled('floor_id') && ! $request->filled('room_id'), fn ($q) => $q->forCampus((int) $request->input('campus_id')));

        $rows = (clone $base)
            ->select('category_id')
            ->selectRaw('COUNT(*) as total_units')
            ->selectRaw('COUNT(DISTINCT COALESCE(group_key, name)) as total_items')
            ->selectRaw('COUNT(DISTINCT room_id) as total_rooms')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $campusRows = (clone $base)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->select('inventory_items.category_id')
            ->selectRaw('COUNT(DISTINCT buildings.campus_id) as total_campuses')
            ->selectRaw('COUNT(DISTINCT buildings.id) as total_buildings')
            ->groupBy('inventory_items.category_id')
            ->get()
            ->keyBy('category_id');

        $stats = [];
        foreach ($categoryIds as $id) {
            $stats[$id] = [
                'total_units' => (int) ($rows[$id]->total_units ?? 0),
                'total_items' => (int) ($rows[$id]->total_items ?? 0),
                'total_rooms' => (int) ($rows[$id]->total_rooms ?? 0),
                'total_campuses' => (int) ($campusRows[$id]->total_campuses ?? 0),
                'total_buildings' => (int) ($campusRows[$id]->total_buildings ?? 0),
            ];
        }

        return $stats;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, InventoryItem>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function buildLocationTree($items): array
    {
        $tree = [];

        foreach ($items as $item) {
            $room = $item->room;
            $floor = $room?->floor;
            $building = $floor?->building;
            $campus = $building?->campus;

            if (! $campus || ! $building || ! $floor || ! $room) {
                continue;
            }

            $cId = $campus->id;
            $bId = $building->id;
            $fId = $floor->id;
            $rId = $room->id;
            $groupKey = $item->group_key ?: 'item-'.$item->id;

            if (! isset($tree[$cId])) {
                $tree[$cId] = [
                    'campus' => $campus,
                    'buildings' => [],
                    'unit_count' => 0,
                ];
            }

            if (! isset($tree[$cId]['buildings'][$bId])) {
                $tree[$cId]['buildings'][$bId] = [
                    'building' => $building,
                    'floors' => [],
                    'unit_count' => 0,
                ];
            }

            if (! isset($tree[$cId]['buildings'][$bId]['floors'][$fId])) {
                $tree[$cId]['buildings'][$bId]['floors'][$fId] = [
                    'floor' => $floor,
                    'rooms' => [],
                    'unit_count' => 0,
                ];
            }

            if (! isset($tree[$cId]['buildings'][$bId]['floors'][$fId]['rooms'][$rId])) {
                $tree[$cId]['buildings'][$bId]['floors'][$fId]['rooms'][$rId] = [
                    'room' => $room,
                    'groups' => [],
                    'unit_count' => 0,
                ];
            }

            if (! isset($tree[$cId]['buildings'][$bId]['floors'][$fId]['rooms'][$rId]['groups'][$groupKey])) {
                $tree[$cId]['buildings'][$bId]['floors'][$fId]['rooms'][$rId]['groups'][$groupKey] = [
                    'name' => $item->name,
                    'group_key' => $item->group_key,
                    'unit' => $item->unit,
                    'items' => [],
                ];
            }

            $tree[$cId]['buildings'][$bId]['floors'][$fId]['rooms'][$rId]['groups'][$groupKey]['items'][] = $item;
            $tree[$cId]['unit_count']++;
            $tree[$cId]['buildings'][$bId]['unit_count']++;
            $tree[$cId]['buildings'][$bId]['floors'][$fId]['unit_count']++;
            $tree[$cId]['buildings'][$bId]['floors'][$fId]['rooms'][$rId]['unit_count']++;
        }

        return $tree;
    }
}
