<?php

namespace App\Http\Controllers;

use App\Exports\InventoryExport;
use App\Models\InventoryItem;
use App\Services\InventoryFilterService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected InventoryFilterService $filterService,
    ) {}

    public function index(): View
    {
        return view('reports.index');
    }

    public function generate(Request $request): View|BinaryFileResponse|Response
    {
        $request->validate([
            'format' => ['required', 'in:view,excel,pdf,csv'],
        ]);

        $query = $this->filterService->apply($request)
            ->with(['category', 'subCategory', 'room.floor.building.campus', 'supplier', 'vendor']);

        $items = $query->orderBy('inventory_number')->get();

        $summary = [
            'total_items' => $items->count(),
            'total_value' => $items->sum('price'),
            'by_status' => $items->groupBy(fn ($i) => $i->status?->value)->map->count(),
            'by_category' => $items->groupBy(fn ($i) => $i->category?->name)->map->count(),
        ];

        return match ($request->input('format')) {
            'excel' => Excel::download(new InventoryExport($request, $this->filterService), 'report_'.now()->format('Ymd_His').'.xlsx'),
            'csv' => Excel::download(new InventoryExport($request, $this->filterService), 'report_'.now()->format('Ymd_His').'.csv', \Maatwebsite\Excel\Excel::CSV),
            'pdf' => Pdf::loadView('exports.inventory-pdf', compact('items', 'summary'))->download('report_'.now()->format('Ymd_His').'.pdf'),
            default => view('reports.result', compact('items', 'summary')),
        };
    }
}
