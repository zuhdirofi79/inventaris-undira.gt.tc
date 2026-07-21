<?php

namespace App\Http\Controllers;

use App\Exports\InventoryHierarchicalExport;
use App\Models\Campus;
use App\Models\Category;
use App\Services\InventoryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected InventoryReportService $reportService,
    ) {}

    public function index(): View
    {
        return view('reports.index', [
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function generate(Request $request): View|BinaryFileResponse|Response
    {
        $request->validate([
            'format' => ['required', 'in:view,print,excel,pdf,csv'],
            'campus_id' => ['nullable', 'integer'],
            'building_id' => ['nullable', 'integer'],
            'floor_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'sub_category_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'condition' => ['nullable', 'string'],
            'purchase_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'purchase_date_from' => ['nullable', 'date'],
            'purchase_date_to' => ['nullable', 'date', 'after_or_equal:purchase_date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $report = $this->reportService->build($request);
        $stamp = now()->format('Ymd_His');

        return match ($request->input('format')) {
            'excel' => Excel::download(
                new InventoryHierarchicalExport($report['tree'], $report['summary'], $report['filters'], $report['meta']),
                "laporan_inventaris_{$stamp}.xlsx"
            ),
            'csv' => Excel::download(
                new InventoryHierarchicalExport($report['tree'], $report['summary'], $report['filters'], $report['meta']),
                "laporan_inventaris_{$stamp}.csv",
                \Maatwebsite\Excel\Excel::CSV
            ),
            'pdf' => Pdf::loadView('exports.inventory-pdf', $report)
                ->setPaper('a4', 'portrait')
                ->download("laporan_inventaris_{$stamp}.pdf"),
            'print' => view('reports.print', $report),
            default => view('reports.result', $report),
        };
    }
}
