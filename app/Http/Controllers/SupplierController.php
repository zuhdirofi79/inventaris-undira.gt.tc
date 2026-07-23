<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Exports\MasterArrayExport;
use App\Http\Requests\ImportMasterRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
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

class SupplierController extends Controller
{
    public function __construct(
        protected TrashCascadeService $trash,
        protected AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $query = Supplier::query()->withCount('inventoryItems');

        MasterListQuery::apply(
            $query,
            $request,
            ['name', 'contact_person', 'email', 'phone'],
            ['name' => 'name', 'email' => 'email', 'created_at' => 'created_at'],
        );

        $suppliers = $query->paginate(20)->withQueryString();

        return view('master.suppliers.index', compact('suppliers'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = Supplier::query();
        MasterListQuery::apply(
            $query,
            $request,
            ['name', 'contact_person', 'email', 'phone'],
            ['name' => 'name', 'email' => 'email', 'created_at' => 'created_at'],
        );

        $rows = $query->get()->map(fn (Supplier $s) => [
            $s->name,
            $s->contact_person,
            $s->phone,
            $s->email,
            $s->address,
            $s->is_active ? 1 : 0,
        ]);

        $this->audit->log($request->user(), AuditAction::Export, null, null, [
            'entity' => 'supplier',
            'format' => 'csv',
            'count' => $rows->count(),
        ], 'SUPPLIER_EXPORTED');

        return Excel::download(
            new MasterArrayExport(
                ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'],
                $rows,
            ),
            'suppliers_'.now()->format('Ymd_His').'.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new MasterArrayExport(
                ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'],
                [['Contoh Supplier', 'Budi', '08123456789', 'budi@example.com', 'Jakarta', 1]],
            ),
            'template_suppliers.csv',
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

                Supplier::query()->updateOrCreate(
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
            'entity' => 'supplier',
            'imported' => $imported,
        ], 'SUPPLIER_IMPORTED');

        return back()->with('success', "Import supplier selesai. {$imported} baris diproses.");
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->validated());

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $supplier,
            null,
            $supplier->only(['name', 'email', 'phone', 'is_active']),
            'SUPPLIER_CREATED',
        );

        return back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $old = $supplier->only(['name', 'email', 'phone', 'is_active']);
        $supplier->update($request->validated());

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $supplier,
            $old,
            $supplier->only(['name', 'email', 'phone', 'is_active']),
            'SUPPLIER_UPDATED',
        );

        return back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->trash->softDelete($supplier, $request->user());

        return back()->with('success', 'Supplier dipindahkan ke Trash.');
    }
}
