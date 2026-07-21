<?php

namespace App\Http\Controllers;

use App\Models\Building;
use Illuminate\View\View;

class BuildingController extends Controller
{
    public function show(Building $building): View
    {
        abort_unless($building->is_active, 404);

        $building->load([
            'campus',
            'floors' => fn ($q) => $q->where('is_active', true)
                ->withCount(['rooms' => fn ($rq) => $rq->where('is_active', true)])
                ->orderBy('floor_number'),
        ]);

        return view('locations.buildings.show', compact('building'));
    }
}
