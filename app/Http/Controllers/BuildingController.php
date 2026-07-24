<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Floor;
use App\Models\InventoryItem;
use Illuminate\View\View;

class BuildingController extends Controller
{
    public function show(Building $building): View
    {
        abort_unless($building->is_active, 404);

        $building->load('campus');

        $floors = Floor::query()
            ->where('building_id', $building->id)
            ->where('is_active', true)
            ->withCount(['rooms' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('floor_number')
            ->orderBy('name')
            ->get();

        $inventoryCounts = InventoryItem::query()
            ->forBuilding($building->id)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->whereNull('rooms.deleted_at')
            ->groupBy('rooms.floor_id')
            ->selectRaw('rooms.floor_id as floor_id, COUNT(inventory_items.id) as total')
            ->pluck('total', 'floor_id');

        $floors = $floors->map(function (Floor $floor) use ($inventoryCounts) {
            $floor->setAttribute('inventory_count', (int) ($inventoryCounts[$floor->id] ?? 0));

            return $floor;
        });

        return view('locations.buildings.show', [
            'building' => $building,
            'floors' => $floors,
        ]);
    }
}
