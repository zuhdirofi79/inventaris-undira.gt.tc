<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFloorRequest;
use App\Models\Building;
use App\Models\Floor;
use App\Services\DashboardTreeService;
use App\Services\TrashCascadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FloorManagementController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
    ) {}

    public function index(Building $building): View
    {
        $floors = $building->floors()->withCount('rooms')->orderBy('floor_number')->paginate(20);

        return view('admin.locations.floors.index', compact('building', 'floors'));
    }

    public function store(StoreFloorRequest $request): RedirectResponse
    {
        Floor::query()->create($request->validated());

        DashboardTreeService::flushCache();

        return back()->with('success', 'Lantai berhasil ditambahkan.');
    }

    public function update(StoreFloorRequest $request, Floor $floor): RedirectResponse
    {
        $floor->update($request->validated());

        DashboardTreeService::flushCache();

        return back()->with('success', 'Lantai berhasil diperbarui.');
    }

    public function destroy(Request $request, Floor $floor): RedirectResponse
    {
        $this->trash->softDelete($floor, $request->user());

        return back()->with('success', 'Lantai dipindahkan ke Trash (beserta ruangan/inventaris turunannya).');
    }
}
