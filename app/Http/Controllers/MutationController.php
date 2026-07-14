<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMutationRequest;
use App\Models\InventoryItem;
use App\Models\InventoryMutation;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MutationController extends Controller
{
    public function index(): View
    {
        $mutations = InventoryMutation::query()
            ->with(['inventoryItem', 'fromRoom.floor.building', 'toRoom.floor.building', 'movedBy'])
            ->latest()
            ->paginate(20);

        return view('operations.mutations.index', compact('mutations'));
    }

    public function create(): View
    {
        return view('operations.mutations.create', [
            'items' => InventoryItem::query()->orderBy('inventory_number')->get(['id', 'inventory_number', 'name', 'room_id']),
            'rooms' => Room::query()->where('is_active', true)->with('floor.building.campus')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMutationRequest $request): RedirectResponse
    {
        $item = InventoryItem::query()->findOrFail($request->input('inventory_item_id'));
        $toRoom = Room::query()->findOrFail($request->input('to_room_id'));

        InventoryMutation::query()->create([
            'inventory_item_id' => $item->id,
            'from_room_id' => $item->room_id,
            'to_room_id' => $toRoom->id,
            'moved_by' => $request->user()->id,
            'reason' => $request->input('reason'),
            'notes' => $request->input('notes'),
        ]);

        $item->update([
            'room_id' => $toRoom->id,
            'updated_by' => $request->user()->id,
        ]);

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
