<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Exports\MasterArrayExport;
use App\Http\Requests\ImportMasterRequest;
use App\Http\Requests\StoreCampusRequest;
use App\Models\Campus;
use App\Services\AuditLogService;
use App\Services\DashboardTreeService;
use App\Services\TrashCascadeService;
use App\Support\MasterCsvReader;
use App\Support\MasterListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function export(Request $request): BinaryFileResponse
    {
        $query = Campus::query();
        MasterListQuery::apply(
            $query,
            $request,
            ['code', 'name', 'slug'],
            ['name' => 'name', 'code' => 'code', 'created_at' => 'created_at'],
        );

        $rows = $query->get()->map(fn (Campus $c) => [
            $c->code,
            $c->name,
            $c->slug,
            $c->description,
            $c->is_active ? 1 : 0,
        ]);

        $this->audit->log($request->user(), AuditAction::Export, null, null, [
            'entity' => 'campus',
            'format' => 'csv',
            'count' => $rows->count(),
        ], 'CAMPUS_EXPORTED');

        return Excel::download(
            new MasterArrayExport(
                ['code', 'name', 'slug', 'description', 'is_active'],
                $rows,
            ),
            'campuses_'.now()->format('Ymd_His').'.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new MasterArrayExport(
                ['code', 'name', 'slug', 'description', 'is_active'],
                [['KP01', 'Kampus Contoh', 'kampus-contoh', 'Deskripsi singkat', 1]],
            ),
            'template_campuses.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function import(ImportMasterRequest $request): RedirectResponse
    {
        $rows = MasterCsvReader::associativeRows($request->file('file'));
        if ($rows === []) {
            return back()->with('error', 'Berkas import kosong atau tidak valid.');
        }

        $imported = 0;

        DB::transaction(function () use ($rows, &$imported) {
            foreach ($rows as $data) {
                if (blank($data['code'] ?? null) || blank($data['name'] ?? null)) {
                    continue;
                }

                $name = (string) $data['name'];
                $slug = filled($data['slug'] ?? null) ? (string) $data['slug'] : Str::slug($name);

                Campus::query()->updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $data['description'] ?? null,
                        'is_active' => MasterCsvReader::parseActive($data['is_active'] ?? null),
                    ],
                );
                $imported++;
            }
        });

        DashboardTreeService::flushCache();

        $this->audit->log($request->user(), AuditAction::Import, null, null, [
            'entity' => 'campus',
            'imported' => $imported,
        ], 'CAMPUS_IMPORTED');

        return back()->with('success', "Import kampus selesai. {$imported} baris diproses.");
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
