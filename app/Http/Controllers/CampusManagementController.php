<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampusRequest;
use App\Models\Campus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CampusManagementController extends Controller
{
    public function index(): View
    {
        $campuses = Campus::query()->withCount('buildings')->orderBy('name')->paginate(20);

        return view('admin.locations.campuses.index', compact('campuses'));
    }

    public function store(StoreCampusRequest $request): RedirectResponse
    {
        Campus::query()->create([
            ...$request->validated(),
            'slug' => $request->input('slug') ?? Str::slug($request->input('name')),
        ]);

        return back()->with('success', 'Kampus berhasil ditambahkan.');
    }

    public function update(StoreCampusRequest $request, Campus $campus): RedirectResponse
    {
        $campus->update($request->validated());

        return back()->with('success', 'Kampus berhasil diperbarui.');
    }

    public function destroy(Campus $campus): RedirectResponse
    {
        if ($campus->buildings()->exists()) {
            return back()->with('error', 'Kampus tidak dapat dihapus karena masih memiliki gedung.');
        }

        $campus->delete();

        return back()->with('success', 'Kampus berhasil dihapus.');
    }
}
