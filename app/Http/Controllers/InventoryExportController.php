<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Exports\InventoryExport;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use App\Services\InventoryFilterService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected InventoryFilterService $filterService,
    ) {}

    public function excel(Request $request): BinaryFileResponse
    {
        $this->auditLogService->log($request->user(), AuditAction::Export, null, null, ['format' => 'excel']);

        return Excel::download(
            new InventoryExport($request, $this->filterService),
            'inventory_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function csv(Request $request): BinaryFileResponse
    {
        $this->auditLogService->log($request->user(), AuditAction::Export, null, null, ['format' => 'csv']);

        return Excel::download(
            new InventoryExport($request, $this->filterService),
            'inventory_'.now()->format('Ymd_His').'.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function pdf(Request $request): Response
    {
        $items = $this->filterService
            ->apply($request)
            ->with(['category', 'room.floor.building.campus'])
            ->orderBy('inventory_number')
            ->get();

        $this->auditLogService->log($request->user(), AuditAction::Export, null, null, ['format' => 'pdf', 'count' => $items->count()]);

        $pdf = Pdf::loadView('exports.inventory-pdf', compact('items'));

        return $pdf->download('inventory_'.now()->format('Ymd_His').'.pdf');
    }
}
