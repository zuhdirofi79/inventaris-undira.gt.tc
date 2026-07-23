<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Exports\InventoryTemplateExport;
use App\Http\Requests\ImportInventoryRequest;
use App\Imports\InventoryImport;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryImportController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function index(): View
    {
        return view('inventory.import');
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new InventoryTemplateExport, 'inventory_import_template.xlsx');
    }

    public function preview(ImportInventoryRequest $request): JsonResponse
    {
        $import = new InventoryImport(previewOnly: true, userId: $request->user()->id);
        Excel::import($import, $request->file('file'));

        return response()->json([
            'preview' => $import->preview,
            'errors' => $import->errors,
            'valid_rows' => count($import->preview),
            'error_rows' => count($import->errors),
        ]);
    }

    public function process(ImportInventoryRequest $request): RedirectResponse|JsonResponse
    {
        $import = new InventoryImport(previewOnly: false, userId: $request->user()->id);

        try {
            DB::beginTransaction();
            Excel::import($import, $request->file('file'));

            if ($import->hasErrors()) {
                DB::rollBack();

                if ($request->expectsJson()) {
                    return response()->json(['errors' => $import->errors], 422);
                }

                return back()->withErrors(['file' => 'Import gagal. Periksa data Excel.'])->with('import_errors', $import->errors);
            }

            DB::commit();

            $this->auditLogService->log(
                $request->user(),
                AuditAction::Import,
                null,
                null,
                ['imported' => $import->imported],
                "Import {$import->imported} item inventaris"
            );

            if ($request->expectsJson()) {
                return response()->json(['imported' => $import->imported]);
            }

            return redirect()->route('inventory.index')->with('success', "{$import->imported} item berhasil diimport.");
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
