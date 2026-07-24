<?php

namespace App\Http\Controllers;

use App\Enums\InventoryReportType;
use App\Exports\EamFlatReportExport;
use App\Exports\InventoryHierarchicalExport;
use App\Services\Reports\EamReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected EamReportService $reports,
    ) {}

    public function index(): View
    {
        return view('reports.dashboard', [
            'types' => InventoryReportType::menuTypes(),
            'dashboard' => $this->reports->dashboard(request()),
        ]);
    }

    public function show(string $type): View|RedirectResponse
    {
        $reportType = InventoryReportType::tryFrom($type);
        if (! $reportType || $reportType === InventoryReportType::Dashboard) {
            return redirect()->route('reports.index');
        }

        if ($reportType === InventoryReportType::Recap) {
            return view('reports.recap', [
                'type' => $reportType,
                'dashboard' => $this->reports->dashboard(request()),
                'options' => $this->reports->filterOptions(),
            ]);
        }

        return view('reports.show', [
            'type' => $reportType,
            'options' => $this->reports->filterOptions(),
        ]);
    }

    public function data(Request $request, string $type): JsonResponse
    {
        $reportType = InventoryReportType::tryFrom($type);
        abort_unless($reportType && $reportType !== InventoryReportType::Dashboard, 404);

        return match ($reportType->family()) {
            'borrowing' => $this->borrowingData($request),
            'maintenance' => $this->maintenanceData($request),
            'mutation' => $this->mutationData($request),
            'dashboard' => response()->json(['data' => []]),
            default => $this->inventoryData($request, $reportType),
        };
    }

    public function export(Request $request, string $type): View|BinaryFileResponse|Response|RedirectResponse|JsonResponse
    {
        $reportType = InventoryReportType::tryFrom($type);
        abort_unless($reportType && ! in_array($reportType, [InventoryReportType::Dashboard, InventoryReportType::Recap], true), 404);

        $request->validate([
            'format' => ['required', 'in:preview,print,excel,csv,pdf,pdf-portrait,pdf-landscape'],
            'orientation' => ['nullable', 'in:portrait,landscape'],
        ]);

        $format = (string) $request->input('format');
        if (in_array($format, ['excel', 'csv', 'pdf', 'pdf-portrait', 'pdf-landscape'], true)) {
            abort_unless($request->user()?->can('report.export'), 403);
        }

        $orientation = $request->input('orientation') === 'landscape' || $format === 'pdf-landscape'
            ? 'landscape'
            : 'portrait';

        return match ($reportType->family()) {
            'borrowing' => $this->exportBorrowing($request, $reportType, $format, $orientation),
            'maintenance' => $this->exportMaintenance($request, $reportType, $format, $orientation),
            'mutation' => $this->exportMutation($request, $reportType, $format, $orientation),
            default => $this->exportInventory($request, $reportType, $format, $orientation),
        };
    }

    /**
     * Legacy POST /reports/generate — map to lengkap export.
     */
    public function generate(Request $request): View|BinaryFileResponse|Response|RedirectResponse|JsonResponse
    {
        $format = (string) $request->input('format', 'view');
        $map = [
            'view' => 'preview',
            'print' => 'print',
            'excel' => 'excel',
            'csv' => 'csv',
            'pdf' => 'pdf-portrait',
            'pdf-portrait' => 'pdf-portrait',
            'pdf-landscape' => 'pdf-landscape',
        ];
        $mapped = $map[$format] ?? 'preview';

        if (in_array($mapped, ['excel', 'csv', 'pdf-portrait', 'pdf-landscape'], true)) {
            abort_unless($request->user()?->can('report.export'), 403);
        }

        $scopeKeys = [
            'campus_id', 'building_id', 'floor_id', 'room_id',
            'category_id', 'sub_category_id', 'status', 'condition',
            'purchase_year', 'purchase_date_from', 'purchase_date_to',
            'tanggal_awal', 'tanggal_akhir', 'search', 'keyword',
            'inventory_number', 'name', 'serial_number', 'merk', 'pic_id',
        ];
        $hasScope = collect($scopeKeys)->contains(fn (string $key) => $request->filled($key));

        if (! $hasScope && in_array($mapped, ['excel', 'csv', 'pdf-portrait', 'pdf-landscape', 'print'], true)) {
            return redirect()
                ->route('reports.index')
                ->with('error', 'Pilih minimal satu filter sebelum mengekspor/mencetak laporan.');
        }

        $request->merge(['format' => $mapped]);

        return $this->export($request, InventoryReportType::Complete->value);
    }

    protected function inventoryData(Request $request, InventoryReportType $type): JsonResponse
    {
        $page = $this->reports->paginateInventory($request, $type);
        $start = ($page->currentPage() - 1) * $page->perPage();

        $rows = collect($page->items())->values()->map(function ($item, $idx) use ($start) {
            return $this->reports->mapInventoryRow($item, $start + $idx + 1);
        });

        $summary = $this->reports->summarizeCollection(collect($page->items()));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'summary' => [
                'page_count' => $page->count(),
                'grand_total' => $page->total(),
                'aktif' => $summary['aktif'] ?? 0,
                'rusak' => $summary['rusak'] ?? 0,
                'dipinjam' => $summary['dipinjam'] ?? 0,
                'maintenance' => $summary['maintenance'] ?? 0,
                'nilai_aset' => $summary['nilai_aset'] ?? 0,
            ],
        ]);
    }

    protected function borrowingData(Request $request): JsonResponse
    {
        $page = $this->reports->borrowingQuery($request)->paginate(max(10, min(100, (int) $request->input('per_page', 50))))->withQueryString();

        $data = collect($page->items())->map(function ($b, $i) use ($page) {
            $start = ($page->currentPage() - 1) * $page->perPage();
            $item = $b->inventoryItem;
            $end = $b->return_date ?? $b->expected_return_date;
            $duration = $b->borrow_date && $end
                ? $b->borrow_date->diffInDays($end).' hari'
                : '—';

            return [
                'no' => $start + $i + 1,
                'borrowing_number' => 'PIN-'.str_pad((string) $b->id, 6, '0', STR_PAD_LEFT),
                'borrower' => $b->borrower_name,
                'item' => $item?->name.' ('.$item?->inventory_number.')',
                'borrow_date' => $b->borrow_date?->format('d/m/Y'),
                'return_date' => $b->return_date?->format('d/m/Y') ?? ($b->expected_return_date?->format('d/m/Y').' (rencana)'),
                'status' => $b->status?->label() ?? $b->status,
                'duration' => $duration,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'summary' => ['grand_total' => $page->total()],
        ]);
    }

    protected function maintenanceData(Request $request): JsonResponse
    {
        $page = $this->reports->maintenanceQuery($request)->paginate(max(10, min(100, (int) $request->input('per_page', 50))))->withQueryString();

        $data = collect($page->items())->map(function ($m, $i) use ($page) {
            $start = ($page->currentPage() - 1) * $page->perPage();
            $item = $m->inventoryItem;
            $duration = '—';
            if ($m->started_at && $m->completed_at) {
                $duration = $m->started_at->diffInDays($m->completed_at).' hari';
            } elseif ($m->service_date && $m->completed_at) {
                $duration = $m->service_date->diffInDays($m->completed_at).' hari';
            }

            return [
                'no' => $start + $i + 1,
                'wo' => $m->maintenance_number,
                'inventory_number' => $item?->inventory_number,
                'name' => $item?->name,
                'location' => $item?->locationDisplayName() ?? '—',
                'service_date' => $m->service_date?->format('d/m/Y'),
                'started_at' => $m->started_at?->format('d/m/Y H:i') ?? '—',
                'completed_at' => $m->completed_at?->format('d/m/Y H:i') ?? '—',
                'technician' => $m->technicianUser?->name ?? ($m->technician ?: '—'),
                'status' => $m->status?->label() ?? '—',
                'duration' => $duration,
                'cost' => 'Rp '.number_format((float) ($m->cost ?? $m->estimated_cost ?? 0), 0, ',', '.'),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'summary' => ['grand_total' => $page->total()],
        ]);
    }

    protected function mutationData(Request $request): JsonResponse
    {
        $page = $this->reports->mutationQuery($request)->paginate(max(10, min(100, (int) $request->input('per_page', 50))))->withQueryString();

        $fmtRoom = function ($room) {
            if (! $room) {
                return '—';
            }

            return trim(($room->code ? $room->code.' — ' : '').$room->name);
        };

        $data = collect($page->items())->map(function ($mut, $i) use ($page, $fmtRoom) {
            $start = ($page->currentPage() - 1) * $page->perPage();
            $item = $mut->inventoryItem;

            return [
                'no' => $start + $i + 1,
                'item' => ($item?->name ?? '—').' ('.$item?->inventory_number.')',
                'from' => $fmtRoom($mut->fromRoom),
                'to' => $fmtRoom($mut->toRoom),
                'date' => $mut->created_at?->format('d/m/Y H:i'),
                'pic_old' => '—',
                'pic_new' => $item?->picDisplayName() ?: ($mut->movedBy?->name ?? '—'),
                'moved_by' => $mut->movedBy?->name ?? '—',
                'reason' => $mut->reason ?: '—',
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'summary' => ['grand_total' => $page->total()],
        ]);
    }

    protected function exportInventory(Request $request, InventoryReportType $type, string $format, string $orientation): View|BinaryFileResponse|Response|RedirectResponse
    {
        $doc = $this->reports->buildInventoryDocument($request, $type, $orientation);
        $stamp = now()->format('Ymd_His');
        $slug = $type->value;

        if ($format === 'preview') {
            return view('reports.preview', $doc);
        }

        if ($format === 'print') {
            return view('reports.print', $doc);
        }

        if (in_array($format, ['pdf', 'pdf-portrait', 'pdf-landscape'], true)) {
            $pdf = Pdf::loadView('exports.inventory-pdf', $doc);
            $pdf->setPaper('a4', $orientation);

            return $pdf->download("laporan_{$slug}_{$orientation}_{$stamp}.pdf");
        }

        $headings = ['No', 'Nomor Inventaris', 'Nama Barang', 'Kategori', 'Subkategori', 'Merk', 'Serial Number', 'Lokasi', 'PIC', 'Status', 'Kondisi', 'Tgl Pengadaan', 'Nilai'];
        $rows = [];
        foreach ($doc['items'] as $i => $item) {
            $mapped = $this->reports->mapInventoryRow($item, $i + 1, false);
            $rows[] = [
                $mapped['no'], $mapped['inventory_number'], $mapped['name'], $mapped['category'], $mapped['sub_category'],
                $mapped['brand'], $mapped['serial_number'], $mapped['location'], $mapped['pic'], $mapped['status'],
                $mapped['condition'], $mapped['purchase_date'], $mapped['price'],
            ];
        }

        if ($type->isHierarchy() && $format === 'excel') {
            return Excel::download(
                new InventoryHierarchicalExport($doc['tree'], $doc['summary'], $doc['filters'], $doc['meta']),
                "laporan_{$slug}_{$stamp}.xlsx"
            );
        }

        $export = new EamFlatReportExport($headings, $rows, $type->label());

        if ($format === 'csv') {
            return Excel::download($export, "laporan_{$slug}_{$stamp}.csv", \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download($export, "laporan_{$slug}_{$stamp}.xlsx");
    }

    protected function exportBorrowing(Request $request, InventoryReportType $type, string $format, string $orientation): View|BinaryFileResponse|Response
    {
        $rows = $this->reports->borrowingQuery($request)->limit(EamReportService::EXPORT_MAX_ROWS)->get();
        $meta = $this->reports->meta($request, $type, $orientation);
        $filters = app(\App\Services\InventoryReportService::class)->describeFilters($request);
        $payload = compact('rows', 'meta', 'filters', 'type');

        if ($format === 'preview') {
            return view('reports.preview-borrowing', $payload);
        }
        if ($format === 'print') {
            return view('reports.print-borrowing', $payload);
        }
        if (str_starts_with($format, 'pdf')) {
            return Pdf::loadView('exports.borrowing-pdf', $payload)->setPaper('a4', $orientation)
                ->download('laporan_peminjaman_'.now()->format('Ymd_His').'.pdf');
        }

        $excelRows = $rows->map(function ($b, $i) {
            $item = $b->inventoryItem;

            return [
                $i + 1,
                'PIN-'.str_pad((string) $b->id, 6, '0', STR_PAD_LEFT),
                $b->borrower_name,
                $item?->name,
                $item?->inventory_number,
                $b->borrow_date?->format('Y-m-d'),
                $b->return_date?->format('Y-m-d'),
                $b->status?->label(),
            ];
        })->all();

        $export = new EamFlatReportExport(
            ['No', 'No Peminjaman', 'Peminjam', 'Barang', 'No Inventaris', 'Tgl Pinjam', 'Tgl Kembali', 'Status'],
            $excelRows,
            'Peminjaman'
        );

        return $format === 'csv'
            ? Excel::download($export, 'laporan_peminjaman.csv', \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, 'laporan_peminjaman.xlsx');
    }

    protected function exportMaintenance(Request $request, InventoryReportType $type, string $format, string $orientation): View|BinaryFileResponse|Response
    {
        $rows = $this->reports->maintenanceQuery($request)->limit(EamReportService::EXPORT_MAX_ROWS)->get();
        $meta = $this->reports->meta($request, $type, $orientation);
        $filters = app(\App\Services\InventoryReportService::class)->describeFilters($request);
        $payload = compact('rows', 'meta', 'filters', 'type');

        if ($format === 'preview') {
            return view('reports.preview-maintenance', $payload);
        }
        if ($format === 'print') {
            return view('reports.print-maintenance', $payload);
        }
        if (str_starts_with($format, 'pdf')) {
            return Pdf::loadView('exports.maintenance-asset-pdf', $payload)->setPaper('a4', $orientation)
                ->download('laporan_maintenance_'.now()->format('Ymd_His').'.pdf');
        }

        $excelRows = $rows->map(function ($m, $i) {
            $item = $m->inventoryItem;

            return [
                $i + 1,
                $m->maintenance_number,
                $item?->inventory_number,
                $item?->name,
                $item?->locationDisplayName(),
                $m->service_date?->format('Y-m-d'),
                $m->started_at?->format('Y-m-d H:i'),
                $m->completed_at?->format('Y-m-d H:i'),
                $m->technicianUser?->name ?? $m->technician,
                $m->status?->label(),
                (float) ($m->cost ?? 0),
            ];
        })->all();

        $export = new EamFlatReportExport(
            ['No', 'No WO', 'No Inventaris', 'Barang', 'Lokasi', 'Tgl Masuk', 'Mulai', 'Selesai', 'Teknisi', 'Status', 'Biaya'],
            $excelRows,
            'Maintenance'
        );

        return $format === 'csv'
            ? Excel::download($export, 'laporan_maintenance.csv', \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, 'laporan_maintenance.xlsx');
    }

    protected function exportMutation(Request $request, InventoryReportType $type, string $format, string $orientation): View|BinaryFileResponse|Response
    {
        $rows = $this->reports->mutationQuery($request)->limit(EamReportService::EXPORT_MAX_ROWS)->get();
        $meta = $this->reports->meta($request, $type, $orientation);
        $filters = app(\App\Services\InventoryReportService::class)->describeFilters($request);
        $payload = compact('rows', 'meta', 'filters', 'type');

        if ($format === 'preview') {
            return view('reports.preview-mutation', $payload);
        }
        if ($format === 'print') {
            return view('reports.print-mutation', $payload);
        }
        if (str_starts_with($format, 'pdf')) {
            return Pdf::loadView('exports.mutation-pdf', $payload)->setPaper('a4', $orientation)
                ->download('laporan_mutasi_'.now()->format('Ymd_His').'.pdf');
        }

        $fmt = fn ($room) => $room ? trim(($room->code ? $room->code.' — ' : '').$room->name) : '—';
        $excelRows = $rows->map(function ($mut, $i) use ($fmt) {
            $item = $mut->inventoryItem;

            return [
                $i + 1,
                $item?->name,
                $item?->inventory_number,
                $fmt($mut->fromRoom),
                $fmt($mut->toRoom),
                $mut->created_at?->format('Y-m-d H:i'),
                $mut->movedBy?->name,
                $item?->picDisplayName(),
                $mut->reason,
            ];
        })->all();

        $export = new EamFlatReportExport(
            ['No', 'Barang', 'No Inventaris', 'Lokasi Lama', 'Lokasi Baru', 'Tanggal', 'PIC/Petugas', 'PIC Barang', 'Alasan'],
            $excelRows,
            'Mutasi'
        );

        return $format === 'csv'
            ? Excel::download($export, 'laporan_mutasi.csv', \Maatwebsite\Excel\Excel::CSV)
            : Excel::download($export, 'laporan_mutasi.xlsx');
    }
}
