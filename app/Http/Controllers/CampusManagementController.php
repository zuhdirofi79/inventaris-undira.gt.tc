<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreCampusRequest;
use App\Models\Campus;
use App\Services\AuditLogService;
use App\Services\DashboardTreeService;
use App\Services\TrashCascadeService;
use App\Support\MasterListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CampusManagementController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
        protected AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $query = Campus::query()->withCount('buildings');

        MasterListQuery::apply(
            $query,
            $request,
            ['code', 'name', 'slug'],
            ['name' => 'name', 'code' => 'code', 'created_at' => 'created_at'],
        );

        $campuses = $query->paginate(20)->withQueryString();

        return view('admin.locations.campuses.index', compact('campuses'));
    }

    public function store(StoreCampusRequest $request): RedirectResponse
    {
        $campus = Campus::query()->create([
            ...$request->validated(),
            'slug' => $request->input('slug') ?? Str::slug($request->input('name')),
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $campus,
            null,
            $campus->only(['code', 'name', 'slug', 'is_active']),
            'CAMPUS_CREATED',
        );

        DashboardTreeService::flushCache();

        return back()->with('success', 'Kampus berhasil ditambahkan.');
    }

    public function update(StoreCampusRequest $request, Campus $campus): RedirectResponse
    {
        $old = $campus->only(['code', 'name', 'slug', 'is_active']);
        $campus->update($request->validated());

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $campus,
            $old,
            $campus->only(['code', 'name', 'slug', 'is_active']),
            'CAMPUS_UPDATED',
        );

        DashboardTreeService::flushCache();

        return back()->with('success', 'Kampus berhasil diperbarui.');
    }

    public function destroy(Request $request, Campus $campus): RedirectResponse
    {
        $this->trash->softDelete($campus, $request->user());

        return back()->with('success', 'Kampus dipindahkan ke Trash (beserta gedung/lantai/ruangan/inventaris turunannya).');
    }
}
