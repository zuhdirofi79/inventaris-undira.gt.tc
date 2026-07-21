<?php

namespace App\Http\Controllers;

use App\Models\Floor;
use Illuminate\View\View;

class FloorController extends Controller
{
    public function show(Floor $floor): View
    {
        abort_unless($floor->is_active, 404);

        $canManageRooms = auth()->user()?->can('location.manage') ?? false;

        $floor->load([
            'building.campus',
            'rooms' => fn ($q) => $q
                ->when(! $canManageRooms, fn ($q) => $q->where('is_active', true))
                ->withCount('inventoryItems')
                ->orderBy('name'),
        ]);

        $roomTypes = [
            'Kelas',
            'Lab',
            'Kantor',
            'Aula',
            'Perpustakaan',
            'Meeting Room',
            'Gudang',
            'Lainnya',
        ];

        return view('locations.floors.show', compact('floor', 'canManageRooms', 'roomTypes'));
    }
}
