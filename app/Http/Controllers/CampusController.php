<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use Illuminate\View\View;

class CampusController extends Controller
{
    public function index(): View
    {
        $campuses = Campus::query()
            ->where('is_active', true)
            ->withCount('buildings')
            ->get();

        return view('locations.campuses.index', compact('campuses'));
    }

    public function show(Campus $campus): View
    {
        $campus->load(['buildings' => fn ($q) => $q->where('is_active', true)->withCount('floors')]);

        return view('locations.campuses.show', compact('campus'));
    }
}
