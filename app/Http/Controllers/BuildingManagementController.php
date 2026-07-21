<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBuildingRequest;
use App\Models\Building;
use App\Models\Campus;
use App\Services\DashboardTreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BuildingManagementController extends Controller
{
    public function index(Campus $campus): View
    {
        $buildings = $campus->buildings()->withCount('floors')->orderBy('name')->paginate(20);

        return view('admin.locations.buildings.index', compact('campus', 'buildings'));
    }

    public function store(StoreBuildingRequest $request): RedirectResponse
    {
        Building::query()->create([
            ...$request->validated(),
            'slug' => $request->input('slug') ?? Str::slug($request->input('name')),
        ]);

        DashboardTreeService::flushCache();

        return back()->with('success', 'Gedung berhasil ditambahkan.');
    }

    public function update(StoreBuildingRequest $request, Building $building): RedirectResponse
    {
        $building->update($request->validated());

        DashboardTreeService::flushCache();

        return back()->with('success', 'Gedung berhasil diperbarui.');
    }

    public function destroy(Building $building): RedirectResponse
    {
        if ($building->floors()->exists()) {
            return back()->with('error', 'Gedung tidak dapat dihapus karena masih memiliki lantai.');
        }

        $building->delete();

        DashboardTreeService::flushCache();

        return back()->with('success', 'Gedung berhasil dihapus.');
    }
}
