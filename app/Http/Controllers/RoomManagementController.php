<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Models\Floor;
use App\Models\Room;
use App\Services\DashboardTreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoomManagementController extends Controller
{
    public function index(Floor $floor): View
    {
        $rooms = $floor->rooms()->withCount('inventoryItems')->orderBy('name')->paginate(20);

        return view('admin.locations.rooms.index', compact('floor', 'rooms'));
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        Room::query()->create($request->validated());

        DashboardTreeService::flushCache();

        return back()->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function update(StoreRoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($request->validated());

        DashboardTreeService::flushCache();

        return back()->with('success', 'Ruangan berhasil diperbarui.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->inventoryItems()->exists()) {
            return back()->with('error', 'Ruangan tidak dapat dihapus karena masih memiliki inventaris.');
        }

        $room->delete();

        DashboardTreeService::flushCache();

        return back()->with('success', 'Ruangan berhasil dihapus.');
    }
}
