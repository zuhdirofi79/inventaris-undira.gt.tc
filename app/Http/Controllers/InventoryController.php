<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\InventoryStatus;
use App\Http\Requests\BulkRelocateInventoryRequest;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Models\Campus;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\Room;
use App\Models\Supplier;
use App\Models\Vendor;
use App\Services\AuditLogService;
use App\Services\InventoryCodeService;
use App\Services\InventoryFilterService;
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
            ->get();

        $stats = [];
        foreach ($campuses as $campus) {
            $base = InventoryItem::query()->forCampus($campus->id);

            $agg = (clone $base)
                ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
                ->join('floors', 'floors.id', '=', 'rooms.floor_id')
                ->join('buildings', 'buildings.id', '=', 'floors.building_id')
                ->selectRaw('COUNT(inventory_items.id) as total_units')
                ->selectRaw('COUNT(DISTINCT COALESCE(inventory_items.group_key, inventory_items.name)) as total_items')
                ->selectRaw('COUNT(DISTINCT floors.id) as floors')
                ->selectRaw('COUNT(DISTINCT rooms.id) as rooms')
                ->first();

            $stats[$campus->id] = [
                'total_units' => (int) ($agg->total_units ?? 0),
                'total_items' => (int) ($agg->total_items ?? 0),
                'floors' => (int) ($agg->floors ?? 0),
                'rooms' => (int) ($agg->rooms ?? 0),
                'available' => (clone $base)->where('status', InventoryStatus::Aktif)->count(),
                'borrowed' => (clone $base)->where('status', InventoryStatus::Dipinjam)->count(),
                'maintenance' => (clone $base)->where('status', InventoryStatus::Maintenance)->count(),
                'damaged' => (clone $base)->whereIn('status', [
                    InventoryStatus::RusakRingan,
                    InventoryStatus::RusakBerat,
                ])->count(),
            ];
        }

        return view('inventory.index', [
            'campuses' => $campuses,
            'stats' => $stats,
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
            ->with([
                'category',
                'subCategory',
                'supplier',
                'vendor',
                'room.floor.building.campus',
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
            'locationData' => $this->locationTree()->where('id', $campus->id)->values(),
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
            ->with([
                'subCategory',
                'supplier',
                'vendor',
                'room.floor.building.campus',
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
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('inventory.create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'locationData' => $this->locationTree(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInventoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $room = Room::query()->findOrFail($data['room_id']);
        $category = Category::query()->findOrFail($data['category_id']);
        $quantity = (int) ($data['quantity'] ?? 1);
        unset($data['quantity']);

        $groupKey = $this->codeService->generateGroupKey($data['name'], $room->id, $category->id);

        DB::transaction(function () use ($data, $room, $category, $quantity, $groupKey, $request) {
            for ($i = 0; $i < $quantity; $i++) {
                $inventoryNumber = $this->codeService->generateInventoryNumber($room, $category, $groupKey);

                $item = InventoryItem::query()->create([
                    ...$data,
                    'inventory_number' => $inventoryNumber,
                    'group_key' => $groupKey,
                    'unit_sequence' => $i + 1,
                    'quantity' => 1,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                $this->codeService->generateAndSaveCodes($item);
                $this->auditLogService->log($request->user(), AuditAction::Create, $item, null, $item->toArray());
            }
        });

        return redirect()
            ->route('inventory.category', $category)
            ->with('success', 'Inventaris berhasil ditambahkan.');
    }

    public function show(InventoryItem $inventory): View
    {
        $inventory->load([
            'category', 'subCategory', 'room.floor.building.campus',
            'supplier', 'vendor', 'photos', 'attachments',
            'mutations.fromRoom', 'mutations.toRoom', 'mutations.movedBy',
            'borrowings', 'maintenances.vendor', 'creator', 'updater',
        ]);

        return view('inventory.show', compact('inventory'));
    }

    public function edit(InventoryItem $inventory): View
    {
        return view('inventory.edit', [
            'inventory' => $inventory->load(['category', 'subCategory', 'room.floor.building.campus']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'locationData' => $this->locationTree(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateInventoryRequest $request, InventoryItem $inventory): RedirectResponse
    {
        $oldValues = $inventory->toArray();
        $inventory->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        $this->auditLogService->log($request->user(), AuditAction::Update, $inventory, $oldValues, $inventory->fresh()->toArray());

        return redirect()->route('inventory.show', $inventory)->with('success', 'Inventaris berhasil diperbarui.');
    }

    public function destroy(Request $request, InventoryItem $inventory): RedirectResponse
    {
        $oldValues = $inventory->toArray();
        $categoryId = $inventory->category_id;
        $inventory->delete();

        $this->auditLogService->log($request->user(), AuditAction::Delete, $inventory, $oldValues, null);

        return redirect()
            ->route('inventory.category', $categoryId)
            ->with('success', 'Inventaris dipindahkan ke trash.');
    }

    public function trash(): View
    {
        $items = InventoryItem::onlyTrashed()
            ->with(['category', 'room.floor.building.campus'])
            ->latest('deleted_at')
            ->paginate(20);

        return view('inventory.trash', compact('items'));
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $inventory = InventoryItem::onlyTrashed()->findOrFail($id);
        $inventory->restore();

        $this->auditLogService->log($request->user(), AuditAction::Restore, $inventory, null, $inventory->toArray());

        return back()->with('success', 'Inventaris berhasil dipulihkan.');
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

        DB::transaction(function () use ($request, $toRoom) {
            $items = InventoryItem::query()->whereIn('id', $request->input('inventory_ids'))->get();

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
            }
        });

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
        $columns = ['inventory_number', 'name', 'category_id', 'status', 'condition', 'price', 'created_at'];
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

    protected function locationTree()
    {
        return Campus::query()
            ->where('is_active', true)
            ->with([
                'buildings' => fn ($q) => $q->where('is_active', true)->orderBy('name')
                    ->with([
                        'floors' => fn ($q) => $q->where('is_active', true)->orderBy('floor_number')
                            ->with([
                                'rooms' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
                            ]),
                    ]),
            ])
            ->orderBy('name')
            ->get();
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
