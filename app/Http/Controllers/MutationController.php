<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreMutationRequest;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\Room;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MutationController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function index(): View
    {
        $mutations = InventoryMutation::query()
            ->with(['inventoryItem', 'fromRoom.floor.building', 'toRoom.floor.building', 'movedBy'])
            ->latest()
            ->paginate(20);

        return view('operations.mutations.index', compact('mutations'));
    }

    public function create(Request $request): View
    {
        $selectedItem = null;
        $selectedRoom = null;
        $selectedItemId = $request->integer('inventory_item_id') ?: (int) old('inventory_item_id');
        $selectedRoomId = (int) old('to_room_id');

        if ($selectedItemId > 0) {
            $selectedItem = InventoryItem::query()->find($selectedItemId, ['id', 'inventory_number', 'name', 'room_id']);
        }
        if ($selectedRoomId > 0) {
            $selectedRoom = Room::query()
                ->with('floor.building.campus')
                ->find($selectedRoomId);
        }

        return view('operations.mutations.create', compact('selectedItem', 'selectedRoom'));
    }

    public function store(StoreMutationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $item = InventoryItem::query()
                ->lockForUpdate()
                ->findOrFail($request->validated('inventory_item_id'));

            $toRoom = Room::query()->findOrFail($request->validated('to_room_id'));
            $fromRoomId = $item->room_id;

            $mutation = InventoryMutation::query()->create([
                'inventory_item_id' => $item->id,
                'from_room_id' => $fromRoomId,
                'to_room_id' => $toRoom->id,
                'moved_by' => $request->user()->id,
                'reason' => $request->validated('reason'),
                'notes' => $request->validated('notes'),
            ]);

            $item->update([
                'room_id' => $toRoom->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->audit->log(
                $request->user(),
                AuditAction::Mutate,
                $mutation,
                [
                    'inventory_item_id' => $item->id,
                    'room_id' => $fromRoomId,
                ],
                [
                    'inventory_item_id' => $item->id,
                    'from_room_id' => $fromRoomId,
                    'to_room_id' => $toRoom->id,
                    'reason' => $request->validated('reason'),
                ],
                'MUTATION_CREATED',
                $request->validated('reason'),
            );
        });

        return redirect()->route('mutations.index')->with('success', 'Mutasi inventaris berhasil dicatat.');
    }

    public function show(InventoryMutation $mutation): View
    {
        $mutation->load([
            'inventoryItem.category',
            'fromRoom.floor.building.campus',
            'toRoom.floor.building.campus',
            'movedBy',
        ]);

        return view('operations.mutations.show', compact('mutation'));
    }
}
