<?php

namespace App\Http\Controllers;

use App\Models\Floor;
use Illuminate\View\View;

class FloorController extends Controller
{
    public function show(Floor $floor): View
    {
        abort_unless($floor->is_active, 404);

        $floor->load([
            'building.campus',
            'rooms' => fn ($q) => $q->where('is_active', true)
                ->withCount('inventoryItems')
                ->orderBy('name'),
        ]);

        return view('locations.floors.show', compact('floor'));
    }
}
