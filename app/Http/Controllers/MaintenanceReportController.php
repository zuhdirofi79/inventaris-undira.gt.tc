<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Exports\MasterArrayExport;
use App\Models\Campus;
use App\Services\MaintenanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MaintenanceReportController extends Controller
{
    public function __construct(
        protected MaintenanceReportService $reports,
    ) {}

    public function index(): View
    {
        abort_unless(request()->user()?->can('maintenance.view') ?? false, 403);

        return view('operations.maintenance-reports.index', [
            'types' => MaintenanceReportService::TYPES,
            'statuses' => MaintenanceStatus::cases(),
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function generate(Request $request): View|Response|BinaryFileResponse|RedirectResponse
    {
        abort_unless($request->user()?->can('maintenance.view') ?? false, 403);

        $filters = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(MaintenanceReportService::TYPES))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'status' => ['nullable', 'string'],
            'format' => ['required', 'string', 'in:view,excel,csv,pdf'],
        ]);

        $format = $filters['format'];
        unset($filters['format']);

        if (in_array($format, ['excel', 'csv', 'pdf'], true)) {
            abort_unless(
                ($request->user()?->can('report.export') ?? false)
                || ($request->user()?->can('maintenance.view') ?? false),
                403
            );
        }

        try {
            $report = $this->reports->build($filters);
            $slug = 'laporan-maintenance-'.$report['type'].'-'.now()->format('Ymd-His');

            return match ($format) {
                'excel' => Excel::download(
                    new MasterArrayExport($report['headings'], $report['rows']),
                    $slug.'.xlsx'
                ),
                'csv' => Excel::download(
                    new MasterArrayExport($report['headings'], $report['rows']),
                    $slug.'.csv',
                    \Maatwebsite\Excel\Excel::CSV
                ),
                'pdf' => Pdf::loadView('exports.maintenance-report-pdf', ['report' => $report])
                    ->setPaper('a4', 'landscape')
                    ->download($slug.'.pdf'),
                default => view('operations.maintenance-reports.result', [
                    'report' => $report,
                    'filters' => $filters,
                ]),
            };
        } catch (Throwable $e) {
            Log::error('Maintenance report generate failed', [
                'user_id' => $request->user()?->id,
                'filters' => $filters,
                'format' => $format,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return back()
                ->withInput()
                ->with('error', 'Gagal menghasilkan laporan: '.$e->getMessage());
        }
    }
}
