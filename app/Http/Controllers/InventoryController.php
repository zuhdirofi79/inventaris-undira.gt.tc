<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\BulkRelocateInventoryRequest;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\Room;
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

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->datatable($request);
        }

        return view('inventory.index');
    }

    public function create(): View
    {
        return view('inventory.create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'rooms' => Room::query()->where('is_active', true)->with('floor.building.campus')->orderBy('name')->get(),
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

        return redirect()->route('inventory.index')->with('success', 'Inventaris berhasil ditambahkan.');
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
            'inventory' => $inventory->load(['category', 'subCategory', 'room']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'rooms' => Room::query()->where('is_active', true)->with('floor.building.campus')->orderBy('name')->get(),
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
        $inventory->delete();

        $this->auditLogService->log($request->user(), AuditAction::Delete, $inventory, $oldValues, null);

        return redirect()->route('inventory.index')->with('success', 'Inventaris dipindahkan ke trash.');
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
            ->with(['room.floor.building.campus', 'category'])
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
            ->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $items,
        ]);
    }
}
