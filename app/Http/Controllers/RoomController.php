<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function show(Room $room): View
    {
        abort_unless($room->is_active, 404);

        $room->load('floor.building.campus');

        $groupedInventory = $room->inventoryItems()
            ->select([
                'group_key',
                'name',
                'category_id',
                DB::raw('COUNT(*) as unit_count'),
                DB::raw('MIN(id) as sample_id'),
            ])
            ->groupBy('group_key', 'name', 'category_id')
            ->with('category')
            ->get();

        $stats = [
            'total_items' => $room->inventoryItems()->count(),
            'total_groups' => $groupedInventory->count(),
            'by_status' => $room->inventoryItems()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];

        return view('locations.rooms.show', compact('room', 'groupedInventory', 'stats'));
    }
}
