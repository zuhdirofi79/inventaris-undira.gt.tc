<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFloorRequest;
use App\Models\Building;
use App\Models\Floor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FloorManagementController extends Controller
{
    public function index(Building $building): View
    {
        $floors = $building->floors()->withCount('rooms')->orderBy('floor_number')->paginate(20);

        return view('admin.locations.floors.index', compact('building', 'floors'));
    }

    public function store(StoreFloorRequest $request): RedirectResponse
    {
        Floor::query()->create($request->validated());

        return back()->with('success', 'Lantai berhasil ditambahkan.');
    }

    public function update(StoreFloorRequest $request, Floor $floor): RedirectResponse
    {
        $floor->update($request->validated());

        return back()->with('success', 'Lantai berhasil diperbarui.');
    }

    public function destroy(Floor $floor): RedirectResponse
    {
        if ($floor->rooms()->exists()) {
            return back()->with('error', 'Lantai tidak dapat dihapus karena masih memiliki ruangan.');
        }

        $floor->delete();

        return back()->with('success', 'Lantai berhasil dihapus.');
    }
}
