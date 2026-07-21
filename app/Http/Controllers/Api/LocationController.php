<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function campuses(): JsonResponse
    {
        $campuses = Campus::query()
            ->where('is_active', true)
            ->withCount('buildings')
            ->orderBy('name')
            ->get();

        return response()->json($campuses);
    }

    public function buildings(Campus $campus): JsonResponse
    {
        $buildings = $campus->buildings()
            ->where('is_active', true)
            ->withCount('floors')
            ->orderBy('name')
            ->get();

        return response()->json($buildings);
    }

    public function floors(Building $building): JsonResponse
    {
        $floors = $building->floors()
            ->where('is_active', true)
            ->withCount('rooms')
            ->orderBy('floor_number')
            ->get();

        return response()->json($floors);
    }

    public function rooms(Floor $floor): JsonResponse
    {
        $rooms = $floor->rooms()
            ->where('is_active', true)
            ->withCount('inventoryItems')
            ->orderBy('name')
            ->get();

        return response()->json($rooms);
    }

    public function roomDetail(Room $room): JsonResponse
    {
        $room->load('floor.building.campus');

        return response()->json([
            'room' => $room,
            'inventory_count' => $room->inventoryItems()->count(),
        ]);
    }
}
