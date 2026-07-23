<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Exports\MasterArrayExport;
use App\Http\Requests\ImportMasterRequest;
use App\Http\Requests\StoreVendorRequest;
use App\Models\Vendor;
use App\Services\AuditLogService;
use App\Services\TrashCascadeService;
use App\Support\MasterCsvReader;
use App\Support\MasterListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VendorController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
        protected AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $query = Vendor::query()->withCount('maintenances');

        MasterListQuery::apply(
            $query,
            $request,
            ['name', 'contact_person', 'email', 'phone'],
            ['name' => 'name', 'email' => 'email', 'created_at' => 'created_at'],
        );

        $vendors = $query->paginate(20)->withQueryString();

        return view('master.vendors.index', compact('vendors'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = Vendor::query();
        MasterListQuery::apply(
            $query,
            $request,
            ['name', 'contact_person', 'email', 'phone'],
            ['name' => 'name', 'email' => 'email', 'created_at' => 'created_at'],
        );

        $rows = $query->get()->map(fn (Vendor $v) => [
            $v->name,
            $v->contact_person,
            $v->phone,
            $v->email,
            $v->address,
            $v->is_active ? 1 : 0,
        ]);

        $this->audit->log($request->user(), AuditAction::Export, null, null, [
            'entity' => 'vendor',
            'format' => 'csv',
            'count' => $rows->count(),
        ], 'VENDOR_EXPORTED');

        return Excel::download(
            new MasterArrayExport(
                ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'],
                $rows,
            ),
            'vendors_'.now()->format('Ymd_His').'.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new MasterArrayExport(
                ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'],
                [['Contoh Vendor', 'Siti', '08129876543', 'siti@example.com', 'Jakarta', 1]],
            ),
            'template_vendors.csv',
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
                if (blank($data['name'] ?? null)) {
                    continue;
                }

                Vendor::query()->updateOrCreate(
                    ['name' => $data['name']],
                    [
                        'contact_person' => $data['contact_person'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'email' => $data['email'] ?? null,
                        'address' => $data['address'] ?? null,
                        'is_active' => MasterCsvReader::parseActive($data['is_active'] ?? null),
                    ],
                );
                $imported++;
            }
        });

        $this->audit->log($request->user(), AuditAction::Import, null, null, [
            'entity' => 'vendor',
            'imported' => $imported,
        ], 'VENDOR_IMPORTED');

        return back()->with('success', "Import vendor selesai. {$imported} baris diproses.");
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $vendor = Vendor::query()->create($request->validated());

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $vendor,
            null,
            $vendor->only(['name', 'email', 'phone', 'is_active']),
            'VENDOR_CREATED',
        );

        return back()->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function update(StoreVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $old = $vendor->only(['name', 'email', 'phone', 'is_active']);
        $vendor->update($request->validated());

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $vendor,
            $old,
            $vendor->only(['name', 'email', 'phone', 'is_active']),
            'VENDOR_UPDATED',
        );

        return back()->with('success', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->trash->softDelete($vendor, $request->user());

        return back()->with('success', 'Vendor dipindahkan ke Trash.');
    }
}
