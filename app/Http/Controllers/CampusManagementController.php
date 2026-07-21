<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampusRequest;
use App\Models\Campus;
use App\Services\DashboardTreeService;
use App\Services\TrashCascadeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CampusManagementController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
    ) {}

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

        DashboardTreeService::flushCache();

        return back()->with('success', 'Kampus berhasil ditambahkan.');
    }

    public function update(StoreCampusRequest $request, Campus $campus): RedirectResponse
    {
        $campus->update($request->validated());

        DashboardTreeService::flushCache();

        return back()->with('success', 'Kampus berhasil diperbarui.');
    }

    public function destroy(Request $request, Campus $campus): RedirectResponse
    {
        $this->trash->softDelete($campus, $request->user());

        return back()->with('success', 'Kampus dipindahkan ke Trash (beserta gedung/lantai/ruangan/inventaris turunannya).');
    }
}
