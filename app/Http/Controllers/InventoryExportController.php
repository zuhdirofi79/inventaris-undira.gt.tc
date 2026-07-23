<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Exports\InventoryHierarchicalExport;
use App\Services\AuditLogService;
use App\Services\InventoryFilterService;
use App\Services\InventoryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryExportController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected InventoryFilterService $filterService,
        protected InventoryReportService $reportService,
    ) {}

    public function excel(Request $request): BinaryFileResponse
    {
        $this->auditLogService->log($request->user(), AuditAction::Export, null, null, ['format' => 'excel']);

        $report = $this->reportService->build($request);

        return Excel::download(
            new InventoryHierarchicalExport($report['tree'], $report['summary'], $report['filters'], $report['meta']),
            'inventory_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function csv(Request $request): BinaryFileResponse
    {
        $this->auditLogService->log($request->user(), AuditAction::Export, null, null, ['format' => 'csv']);

        $report = $this->reportService->build($request);

        return Excel::download(
            new InventoryHierarchicalExport($report['tree'], $report['summary'], $report['filters'], $report['meta']),
            'inventory_'.now()->format('Ymd_His').'.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function pdf(Request $request): Response
    {
        $report = $this->reportService->build($request);

        $this->auditLogService->log(
            $request->user(),
            AuditAction::Export,
            null,
            null,
            ['format' => 'pdf', 'count' => $report['summary']['total_items'] ?? 0]
        );

        return Pdf::loadView('exports.inventory-pdf', $report)
            ->setPaper('a4', 'portrait')
            ->download('inventory_'.now()->format('Ymd_His').'.pdf');
    }
}
