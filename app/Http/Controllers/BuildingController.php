<?php

namespace App\Http\Controllers;

use App\Models\Building;
use Illuminate\View\View;

class BuildingController extends Controller
{
    public function show(Building $building): View
    {
        $building->load([
            'campus',
            'floors' => fn ($q) => $q->where('is_active', true)->withCount('rooms')->orderBy('floor_number'),
        ]);

        return view('locations.buildings.show', compact('building'));
    }
}
