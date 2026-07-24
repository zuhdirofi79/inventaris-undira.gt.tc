<?php

namespace App\Http\Controllers;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationExploreController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $query = $this->filteredItems($request);

        $statusCounts = (clone $query)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $damaged = collect(InventoryStatus::damagedValues())
            ->sum(fn (string $value) => (int) ($statusCounts[$value] ?? 0));
        $inactive = collect(InventoryStatus::terminalValues())
            ->sum(fn (string $value) => (int) ($statusCounts[$value] ?? 0));

        return response()->json([
            'total' => (int) array_sum($statusCounts->all()),
            'aktif' => (int) ($statusCounts[InventoryStatus::Aktif->value] ?? 0),
            'dipinjam' => (int) ($statusCounts[InventoryStatus::Dipinjam->value] ?? 0),
            'maintenance' => (int) ($statusCounts[InventoryStatus::Maintenance->value] ?? 0),
            'rusak' => $damaged,
            'non_aktif' => $inactive,
        ]);
    }

    public function campuses(Request $request): JsonResponse
    {
        $counts = $this->filteredItems($request)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->whereNull('rooms.deleted_at')
            ->whereNull('floors.deleted_at')
            ->whereNull('buildings.deleted_at')
            ->groupBy('buildings.campus_id')
            ->selectRaw('buildings.campus_id as campus_id, COUNT(inventory_items.id) as unit_count')
            ->pluck('unit_count', 'campus_id');

        $buildingCounts = Building::query()
            ->where('is_active', true)
            ->groupBy('campus_id')
            ->selectRaw('campus_id, COUNT(*) as total')
            ->pluck('total', 'campus_id');

        $floorCounts = Floor::query()
            ->where('floors.is_active', true)
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->where('buildings.is_active', true)
            ->whereNull('buildings.deleted_at')
            ->groupBy('buildings.campus_id')
            ->selectRaw('buildings.campus_id as campus_id, COUNT(floors.id) as total')
            ->pluck('total', 'campus_id');

        $roomCounts = Room::query()
            ->where('rooms.is_active', true)
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->where('floors.is_active', true)
            ->where('buildings.is_active', true)
            ->whereNull('floors.deleted_at')
            ->whereNull('buildings.deleted_at')
            ->groupBy('buildings.campus_id')
            ->selectRaw('buildings.campus_id as campus_id, COUNT(rooms.id) as total')
            ->pluck('total', 'campus_id');

        $campuses = Campus::query()
            ->where('is_active', true)
            ->when($request->filled('campus_id'), fn ($q) => $q->where('id', (int) $request->input('campus_id')))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Campus $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'unit_count' => (int) ($counts[$c->id] ?? 0),
                'building_count' => (int) ($buildingCounts[$c->id] ?? 0),
                'floor_count' => (int) ($floorCounts[$c->id] ?? 0),
                'room_count' => (int) ($roomCounts[$c->id] ?? 0),
            ]);

        if ($this->hasActiveFilters($request)) {
            $campuses = $campuses->filter(fn (array $row) => $row['unit_count'] > 0)->values();
        } else {
            $campuses = $campuses->values();
        }

        return response()->json(['nodes' => $campuses]);
    }

    public function buildings(Request $request, Campus $campus): JsonResponse
    {
        abort_unless($campus->is_active, 404);

        $counts = $this->filteredItems($request)
            ->forCampus($campus->id)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->whereNull('rooms.deleted_at')
            ->whereNull('floors.deleted_at')
            ->groupBy('floors.building_id')
            ->selectRaw('floors.building_id as building_id, COUNT(inventory_items.id) as unit_count')
            ->pluck('unit_count', 'building_id');

        $buildings = Building::query()
            ->where('campus_id', $campus->id)
            ->where('is_active', true)
            ->when($request->filled('building_id'), fn ($q) => $q->where('id', (int) $request->input('building_id')))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Building $b) => [
                'id' => $b->id,
                'code' => $b->code,
                'name' => $b->name,
                'unit_count' => (int) ($counts[$b->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        return response()->json(['nodes' => $buildings]);
    }

    public function floors(Request $request, Building $building): JsonResponse
    {
        abort_unless($building->is_active, 404);

        $counts = $this->filteredItems($request)
            ->forBuilding($building->id)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->whereNull('rooms.deleted_at')
            ->groupBy('rooms.floor_id')
            ->selectRaw('rooms.floor_id as floor_id, COUNT(inventory_items.id) as unit_count')
            ->pluck('unit_count', 'floor_id');

        $floors = Floor::query()
            ->where('building_id', $building->id)
            ->where('is_active', true)
            ->when($request->filled('floor_id'), fn ($q) => $q->where('id', (int) $request->input('floor_id')))
            ->orderBy('floor_number')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'floor_number'])
            ->map(fn (Floor $f) => [
                'id' => $f->id,
                'code' => $f->code,
                'name' => $f->name,
                'unit_count' => (int) ($counts[$f->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        return response()->json(['nodes' => $floors]);
    }

    public function rooms(Request $request, Floor $floor): JsonResponse
    {
        abort_unless($floor->is_active, 404);

        $counts = $this->filteredItems($request)
            ->forFloor($floor->id)
            ->whereNotNull('room_id')
            ->groupBy('room_id')
            ->selectRaw('room_id, COUNT(*) as unit_count')
            ->pluck('unit_count', 'room_id');

        $nodes = Room::query()
            ->where('floor_id', $floor->id)
            ->whereIn('id', $counts->keys())
            ->where('is_active', true)
            ->when($request->filled('room_id'), fn ($q) => $q->where('id', (int) $request->input('room_id')))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Room $r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'unit_count' => (int) ($counts[$r->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        return response()->json(['nodes' => $nodes]);
    }

    public function categories(Request $request, Room $room): JsonResponse
    {
        abort_unless($room->is_active, 404);

        $counts = $this->filteredItems($request)
            ->where('room_id', $room->id)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, COUNT(*) as unit_count')
            ->pluck('unit_count', 'category_id');

        $categories = Category::query()
            ->whereIn('id', $counts->keys())
            ->where('is_active', true)
            ->when($request->filled('category_id'), fn ($q) => $q->where('id', (int) $request->input('category_id')))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'unit_count' => (int) ($counts[$c->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        return response()->json(['nodes' => $categories]);
    }

    public function subCategories(Request $request, Room $room, Category $category): JsonResponse
    {
        abort_unless($room->is_active, 404);

        $base = $this->filteredItems($request)
            ->where('room_id', $room->id)
            ->where('category_id', $category->id);

        $counts = (clone $base)
            ->whereNotNull('sub_category_id')
            ->groupBy('sub_category_id')
            ->selectRaw('sub_category_id, COUNT(*) as unit_count')
            ->pluck('unit_count', 'sub_category_id');

        $nodes = SubCategory::query()
            ->where('category_id', $category->id)
            ->whereIn('id', $counts->keys())
            ->where('is_active', true)
            ->when($request->filled('sub_category_id'), fn ($q) => $q->where('id', (int) $request->input('sub_category_id')))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (SubCategory $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'unit_count' => (int) ($counts[$s->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        $uncategorized = (clone $base)
            ->where(fn ($q) => $q->whereNull('sub_category_id')->orWhere('sub_category_id', 0))
            ->count();

        if ($uncategorized > 0 && ! $request->filled('sub_category_id')) {
            $nodes->push([
                'id' => 0,
                'code' => null,
                'name' => 'Tanpa Sub Kategori',
                'unit_count' => $uncategorized,
            ]);
        }

        return response()->json(['nodes' => $nodes]);
    }

    public function items(Request $request, Room $room, Category $category, int $subCategory): JsonResponse
    {
        abort_unless($room->is_active, 404);

        $query = $this->filteredItems($request)
            ->where('room_id', $room->id)
            ->where('category_id', $category->id);

        if ($subCategory > 0) {
            $sub = SubCategory::query()->findOrFail($subCategory);
            abort_unless((int) $sub->category_id === (int) $category->id, 404);
            $query->where('sub_category_id', $sub->id);
        } else {
            $query->where(fn ($q) => $q->whereNull('sub_category_id')->orWhere('sub_category_id', 0));
        }

        $perPage = 50;
        $paginator = $query
            ->with([
                'photos' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id'),
                'pic:id,name',
            ])
            ->select([
                'id', 'name', 'inventory_number', 'serial_number', 'unit_sequence',
                'status', 'condition', 'responsible_person', 'pic_id', 'group_key',
            ])
            ->orderBy('name')
            ->orderBy('unit_sequence')
            ->paginate($perPage)
            ->withQueryString();

        $user = $request->user();
        $canBorrow = $user?->can('borrowing.create') ?? false;
        $canMaintenance = $user?->can('maintenance.create') ?? false;
        $canMutation = $user?->can('mutation.create') ?? false;

        $rows = collect($paginator->items())->map(function (InventoryItem $item) use ($canBorrow, $canMaintenance, $canMutation) {
            $status = $item->status instanceof InventoryStatus ? $item->status : InventoryStatus::tryFrom((string) $item->status);
            $condition = $item->condition instanceof InventoryCondition ? $item->condition : InventoryCondition::tryFrom((string) $item->condition);
            $photo = $item->photos->first();

            return [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => str_pad((string) ($item->unit_sequence ?? 1), 3, '0', STR_PAD_LEFT),
                'inventory_number' => $item->inventory_number,
                'serial_number' => $item->serial_number ?: '—',
                'status' => $status?->value,
                'status_label' => $status?->label() ?? (string) $item->status,
                'status_tone' => $status?->badgeTone() ?? 'unknown',
                'condition' => $condition?->value,
                'condition_label' => $condition?->label() ?? (string) $item->condition,
                'condition_tone' => $condition?->badgeTone() ?? 'unknown',
                'pic' => $item->picDisplayName() ?: '—',
                'photo_url' => $photo?->url,
                'urls' => [
                    'show' => route('inventory.show', $item),
                    'qr' => route('inventory.show', $item).'#qr-section',
                    'history' => route('inventory.show', $item).'#timeline',
                    'maintenance' => $canMaintenance ? route('maintenances.create', ['inventory_item_id' => $item->id]) : null,
                    'mutation' => $canMutation ? route('mutations.create', ['inventory_item_id' => $item->id]) : null,
                    'borrowing' => $canBorrow ? route('borrowings.create', ['inventory_item_id' => $item->id]) : null,
                ],
            ];
        });

        return response()->json([
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $items = $this->filteredItems($request)
            ->with([
                'category:id,code,name',
                'subCategory:id,code,name',
                'pic:id,name',
                'room:id,code,name,floor_id',
                'room.floor:id,code,name,building_id',
                'room.floor.building:id,code,name,campus_id',
                'room.floor.building.campus:id,code,name',
            ])
            ->where(function (Builder $builder) use ($q) {
                $builder->where('inventory_number', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%")
                    ->orWhere('responsible_person', 'like', "%{$q}%")
                    ->orWhereHas('pic', fn (Builder $p) => $p->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                    ->orWhereHas('subCategory', fn (Builder $s) => $s->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                    ->orWhereHas('room', function (Builder $r) use ($q) {
                        $r->where('name', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%")
                            ->orWhereHas('floor', function (Builder $f) use ($q) {
                                $f->where('name', 'like', "%{$q}%")
                                    ->orWhereHas('building', function (Builder $b) use ($q) {
                                        $b->where('name', 'like', "%{$q}%")
                                            ->orWhereHas('campus', fn (Builder $c) => $c->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
                                    });
                            });
                    });
            })
            ->orderBy('inventory_number')
            ->limit(40)
            ->get([
                'id', 'name', 'inventory_number', 'serial_number', 'status', 'condition',
                'responsible_person', 'pic_id', 'room_id', 'category_id', 'sub_category_id', 'unit_sequence',
            ]);

        return response()->json([
            'results' => $items->map(function (InventoryItem $item) {
                $status = $item->status instanceof InventoryStatus ? $item->status : null;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'inventory_number' => $item->inventory_number,
                    'serial_number' => $item->serial_number ?: '—',
                    'status_label' => $status?->label() ?? (string) $item->status,
                    'location' => $item->full_location ?: '—',
                    'category' => $item->category?->name,
                    'sub_category' => $item->subCategory?->name,
                    'url' => route('inventory.show', $item),
                    'path' => [
                        'campus_id' => $item->room?->floor?->building?->campus_id,
                        'building_id' => $item->room?->floor?->building_id,
                        'floor_id' => $item->room?->floor_id,
                        'room_id' => $item->room_id,
                        'category_id' => $item->category_id,
                        'sub_category_id' => $item->sub_category_id ?: 0,
                    ],
                ];
            }),
        ]);
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $campusId = $request->integer('campus_id');
        $buildingId = $request->integer('building_id');
        $floorId = $request->integer('floor_id');
        $categoryId = $request->integer('category_id');

        $campuses = Campus::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $buildings = Building::query()
            ->where('is_active', true)
            ->when($campusId > 0, fn ($q) => $q->where('campus_id', $campusId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'campus_id']);
        $floors = Floor::query()
            ->where('is_active', true)
            ->when($buildingId > 0, fn ($q) => $q->where('building_id', $buildingId))
            ->when($campusId > 0 && $buildingId < 1, fn ($q) => $q->whereHas('building', fn ($b) => $b->where('campus_id', $campusId)))
            ->orderBy('floor_number')
            ->get(['id', 'name', 'code', 'building_id']);
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $subs = SubCategory::query()
            ->where('is_active', true)
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'category_id']);

        return response()->json([
            'campuses' => $campuses,
            'buildings' => $buildings,
            'floors' => $floors,
            'categories' => $categories,
            'sub_categories' => $subs,
            'statuses' => collect(InventoryStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'conditions' => collect(InventoryCondition::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
        ]);
    }

    /**
     * @return Builder<\App\Models\InventoryItem>
     */
    protected function filteredItems(Request $request): Builder
    {
        $query = InventoryItem::query();

        if ($request->filled('campus_id')) {
            $query->forCampus((int) $request->input('campus_id'));
        }
        if ($request->filled('building_id')) {
            $query->forBuilding((int) $request->input('building_id'));
        }
        if ($request->filled('floor_id')) {
            $query->forFloor((int) $request->input('floor_id'));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }
        if ($request->filled('sub_category_id')) {
            $query->where('sub_category_id', (int) $request->input('sub_category_id'));
        }
        if ($request->filled('room_id')) {
            $query->where('room_id', (int) $request->input('room_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->input('condition'));
        }
        if ($request->filled('q') && mb_strlen(trim((string) $request->input('q'))) >= 2) {
            $q = trim((string) $request->input('q'));
            $query->where(function (Builder $builder) use ($q) {
                $builder->where('inventory_number', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        return $query;
    }

    protected function hasActiveFilters(Request $request): bool
    {
        return $request->filled('campus_id')
            || $request->filled('building_id')
            || $request->filled('floor_id')
            || $request->filled('category_id')
            || $request->filled('sub_category_id')
            || $request->filled('room_id')
            || $request->filled('status')
            || $request->filled('condition')
            || ($request->filled('q') && mb_strlen(trim((string) $request->input('q'))) >= 2);
    }
}
