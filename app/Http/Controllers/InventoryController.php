<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\InventoryStatus;
use App\Http\Requests\BulkRelocateInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Models\Campus;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\Vendor;
use App\Services\AuditLogService;
use App\Services\DashboardStatsService;
use App\Services\DashboardTreeService;
use App\Services\InventoryCodeService;
use App\Services\InventoryFilterService;
use App\Services\LocationHierarchyService;
use App\Services\TrashCascadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected InventoryCodeService $codeService,
        protected InventoryFilterService $filterService,
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
        $query = InventoryItem::query()
            ->select(['id', 'inventory_number', 'name', 'room_id', 'status']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('inventory_number', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        $items = $query->orderBy('inventory_number')->limit(30)->get();

        return response()->json([
            'results' => $items->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'text' => $item->inventory_number.' - '.$item->name,
                'room_id' => $item->room_id,
            ]),
        ]);
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
     * Hierarchical browse for one campus: Building → Floor → Room → Units.
     */
    public function byCampus(Request $request, Campus $campus): View
    {
        $itemsQuery = InventoryItem::query()
            ->forCampus($campus->id)
            ->select([
                'id', 'name', 'inventory_number', 'group_key', 'unit_sequence',
                'status', 'condition', 'room_id', 'category_id', 'sub_category_id',
            ])
            ->with([
                'category:id,code,name',
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
            ->get();

        $tree = $this->buildLocationTree($items);

        $summary = [
            'total_units' => $items->count(),
            'total_groups' => $items->groupBy(fn ($i) => $i->group_key ?: $i->name)->count(),
            'buildings' => $items->pluck('room.floor.building.id')->unique()->filter()->count(),
            'floors' => $items->pluck('room.floor.id')->unique()->filter()->count(),
            'rooms' => $items->pluck('room_id')->unique()->filter()->count(),
        ];

        return view('inventory.campus', [
            'campus' => $campus,
            'tree' => $tree,
            'summary' => $summary,
            'locationData' => $this->locationTree($campus->id),
            'filters' => $request->only(['search', 'building_id', 'floor_id', 'room_id']),
        ]);
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
            ->get();

        $tree = $this->buildLocationTree($items);

        $summary = [
            'total_units' => $items->count(),
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
        if ($request->ajax()) {
            return $this->datatable($request);
        }

        return view('inventory.items', [
            'locationData' => $this->locationTree(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'orgUnits' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
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
            'orgUnit', 'procurementType',
            'supplier', 'vendor', 'creator', 'updater',
            'photos' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id'),
            'attachments' => fn ($q) => $q->latest()->limit(50),
            'mutations' => fn ($q) => $q->with(['fromRoom', 'toRoom', 'movedBy'])->latest()->limit(25),
            'borrowings' => fn ($q) => $q->with('approvedBy')->latest()->limit(25),
            'maintenances' => fn ($q) => $q->with('vendor')->latest()->limit(25),
        ]);

        $timeline = app(\App\Services\InventoryHistoryService::class)->timeline($inventory);

        return view('inventory.show', compact('inventory', 'timeline'));
    }

    public function edit(InventoryItem $inventory): View
    {
        return view('inventory.edit', [
            'inventory' => $inventory->load(['category', 'subCategory', 'room.floor.building.campus']),
            ...$this->formLookups(),
        ]);
    }

    public function update(UpdateInventoryRequest $request, InventoryItem $inventory): RedirectResponse
    {
        $oldValues = $inventory->toArray();
        $data = $this->syncProcurementFunding($request->validated());
        unset($data['quantity']);

        $inventory->update([
            ...$data,
            'quantity' => 1,
            'updated_by' => $request->user()->id,
        ]);

        $this->auditLogService->log($request->user(), AuditAction::Update, $inventory, $oldValues, $inventory->fresh()->toArray());

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
        $query = $this->filterService->apply($request)
            ->with(['category', 'subCategory', 'room.floor.building.campus']);

        $recordsTotal = InventoryItem::count();
        $recordsFiltered = (clone $query)->count();

        $orderColumn = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'asc');
        $columns = ['inventory_number', 'name', 'category_id', 'status', 'condition', 'created_at'];
        $orderBy = $columns[$orderColumn] ?? 'inventory_number';

        $items = $query
            ->orderBy($orderBy, $orderDir)
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

                return $data;
            });

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $items,
        ]);
    }

    protected function locationTree(?int $campusId = null)
    {
        return $this->locationHierarchy->activeTree($campusId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formLookups(): array
    {
        return [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'locationData' => $this->locationTree(),
            'orgUnits' => OrgUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'procurementTypes' => ProcurementType::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
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
