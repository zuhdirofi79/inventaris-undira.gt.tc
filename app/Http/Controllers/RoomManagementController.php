<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Models\Floor;
use App\Models\Room;
use App\Services\DashboardTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomManagementController extends Controller
{
    public function index(Floor $floor): View
    {
        $rooms = $floor->rooms()->withCount('inventoryItems')->orderBy('name')->paginate(20);

        return view('admin.locations.rooms.index', compact('floor', 'rooms'));
    }

    public function store(StoreRoomRequest $request): JsonResponse|RedirectResponse
    {
        $room = Room::query()->create($request->validated());
        $room->loadCount('inventoryItems');

        DashboardTreeService::flushCache();

        return $this->respondRoom($request, $room, 'Ruangan berhasil ditambahkan.', 201);
    }

    public function update(StoreRoomRequest $request, Room $room): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        unset($data['floor_id']);

        $room->update($data);
        $room->loadCount('inventoryItems');

        DashboardTreeService::flushCache();

        return $this->respondRoom($request, $room, 'Ruangan berhasil diperbarui.');
    }

    public function destroy(Request $request, Room $room): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->can('location.manage'), 403);

        if ($room->inventoryItems()->exists()) {
            $message = 'Ruangan masih memiliki inventaris. Pindahkan atau hapus inventaris terlebih dahulu.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $roomId = $room->id;
        $room->delete();

        DashboardTreeService::flushCache();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Ruangan berhasil dihapus.',
                'data' => ['id' => $roomId],
            ]);
        }

        return back()->with('success', 'Ruangan berhasil dihapus.');
    }

    public function duplicate(Request $request, Room $room): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->can('location.manage'), 403);

        $baseCode = substr($room->code, 0, 16);
        $code = $baseCode.'-C';
        $i = 1;
        while (
            Room::query()
                ->where('floor_id', $room->floor_id)
                ->where('code', $code)
                ->exists()
        ) {
            $code = $baseCode.'-C'.$i;
            $i++;
            if ($i > 50) {
                $code = substr(uniqid('R'), 0, 20);
                break;
            }
        }

        $copy = Room::query()->create([
            'floor_id' => $room->floor_id,
            'code' => $code,
            'name' => $room->name.' (Salinan)',
            'room_type' => $room->room_type,
            'capacity' => max(1, (int) ($room->capacity ?: 1)),
            'is_active' => $room->is_active,
            'notes' => $room->notes,
        ]);
        $copy->loadCount('inventoryItems');

        DashboardTreeService::flushCache();

        return $this->respondRoom($request, $copy, 'Ruangan berhasil diduplikasi.', 201);
    }

    private function respondRoom(Request $request, Room $room, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'data' => $this->roomPayload($room),
            ], $status);
        }

        return back()->with('success', $message);
    }

    private function roomPayload(Room $room): array
    {
        return [
            'id' => $room->id,
            'floor_id' => $room->floor_id,
            'code' => $room->code,
            'name' => $room->name,
            'room_type' => $room->room_type,
            'capacity' => $room->capacity,
            'is_active' => (bool) $room->is_active,
            'notes' => $room->notes,
            'inventory_items_count' => (int) ($room->inventory_items_count ?? 0),
            'urls' => [
                'show' => route('rooms.show', $room),
                'update' => route('admin.locations.rooms.update', $room),
                'destroy' => route('admin.locations.rooms.destroy', $room),
                'duplicate' => route('admin.locations.rooms.duplicate', $room),
            ],
        ];
    }
}
