<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBuildingRequest;
use App\Models\Building;
use App\Models\Campus;
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

        return back()->with('success', 'Gedung berhasil ditambahkan.');
    }

    public function update(StoreBuildingRequest $request, Building $building): RedirectResponse
    {
        $building->update($request->validated());

        return back()->with('success', 'Gedung berhasil diperbarui.');
    }

    public function destroy(Building $building): RedirectResponse
    {
        $building->delete();

        return back()->with('success', 'Gedung berhasil dihapus.');
    }
}
