<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use Illuminate\View\View;

class CampusController extends Controller
{
    public function index(): View
    {
        $counts = InventoryItem::query()
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
                'accent' => $this->campusAccent($c),
            ]);

        return view('locations.campuses.index', [
            'campuses' => $campuses,
        ]);
    }

    public function show(Campus $campus): View
    {
        abort_unless($campus->is_active, 404);

        $buildings = Building::query()
            ->where('campus_id', $campus->id)
            ->where('is_active', true)
            ->withCount(['floors' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $roomCounts = Room::query()
            ->where('rooms.is_active', true)
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->where('buildings.campus_id', $campus->id)
            ->where('floors.is_active', true)
            ->whereNull('floors.deleted_at')
            ->whereNull('buildings.deleted_at')
            ->groupBy('floors.building_id')
            ->selectRaw('floors.building_id as building_id, COUNT(rooms.id) as total')
            ->pluck('total', 'building_id');

        $inventoryCounts = InventoryItem::query()
            ->forCampus($campus->id)
            ->join('rooms', 'rooms.id', '=', 'inventory_items.room_id')
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->whereNull('rooms.deleted_at')
            ->whereNull('floors.deleted_at')
            ->groupBy('floors.building_id')
            ->selectRaw('floors.building_id as building_id, COUNT(inventory_items.id) as total')
            ->pluck('total', 'building_id');

        $buildings = $buildings->map(function (Building $building) use ($roomCounts, $inventoryCounts) {
            $building->setAttribute('rooms_count', (int) ($roomCounts[$building->id] ?? 0));
            $building->setAttribute('inventory_count', (int) ($inventoryCounts[$building->id] ?? 0));

            return $building;
        });

        return view('locations.campuses.show', [
            'campus' => $campus,
            'buildings' => $buildings,
            'accent' => $this->campusAccent($campus),
        ]);
    }

    protected function campusAccent(Campus $campus): string
    {
        $seed = strtolower(($campus->code ?? '').' '.($campus->name ?? ''));
        if (str_contains($seed, 'cbb') || str_contains($seed, 'cibubur')) {
            return '#1F3C88';
        }
        if (str_contains($seed, 'grn') || str_contains($seed, 'green')) {
            return '#2E7D32';
        }
        if (str_contains($seed, 'tjd') || str_contains($seed, 'tanjung') || str_contains($seed, 'duren')) {
            return '#6D28D9';
        }

        $palette = ['#1F3C88', '#0F766E', '#B45309', '#BE185D', '#0369A1'];

        return $palette[((int) $campus->id) % count($palette)];
    }
}
