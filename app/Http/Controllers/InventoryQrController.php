<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use App\Services\InventoryCodeService;
use App\Services\InventoryFilterService;
use App\Services\InventoryMassLabelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryQrController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected InventoryCodeService $codeService,
        protected InventoryFilterService $filterService,
        protected InventoryMassLabelService $massLabelService,
    ) {}

    public function scan(string $inventoryNumber): View|RedirectResponse
    {
        $inventory = InventoryItem::query()
            ->where(function ($query) use ($inventoryNumber) {
                $query->where('inventory_number', $inventoryNumber)
                    ->orWhere('barcode', $inventoryNumber);
            })
            ->with([
                'category',
                'subCategory',
                'room.floor.building.campus',
                'orgUnit',
                'procurementType',
                'photos' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id'),
                'mutations' => fn ($q) => $q->with(['fromRoom', 'toRoom', 'movedBy'])->latest()->limit(10),
                'maintenances' => fn ($q) => $q->with('vendor')->latest()->limit(10),
            ])
            ->firstOrFail();

        $this->codeService->ensureCodes($inventory);
        $inventory->refresh()->loadMissing([
            'category', 'subCategory', 'room.floor.building.campus',
            'orgUnit', 'procurementType', 'photos', 'mutations.fromRoom', 'mutations.toRoom',
            'mutations.movedBy', 'maintenances.vendor',
        ]);

        if (auth()->check()) {
            $this->auditLogService->log(auth()->user(), AuditAction::ScanQr, $inventory, null, null, "Scan QR {$inventoryNumber}");

            if (auth()->user()->can('inventory.view')) {
                return redirect()->route('inventory.show', $inventory);
            }
        }

        return view('inventory.scan', compact('inventory'));
    }

    public function inlineQr(InventoryItem $inventory): Response
    {
        $this->codeService->ensureCodes($inventory);
        $inventory->refresh();

        if (! $inventory->qr_code_path || ! Storage::disk('public')->exists($inventory->qr_code_path)) {
            abort(404, 'QR Code tidak tersedia.');
        }

        $filename = str_replace(['/', '\\'], '-', $inventory->inventory_number).'-qr.svg';

        return response(Storage::disk('public')->get($inventory->qr_code_path), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function downloadQr(InventoryItem $inventory): StreamedResponse|RedirectResponse
    {
        $this->codeService->ensureCodes($inventory);
        $inventory->refresh();

        if (! $inventory->qr_code_path || ! Storage::disk('public')->exists($inventory->qr_code_path)) {
            return back()->with('error', 'QR Code tidak tersedia.');
        }

        $this->auditLogService->log(
            auth()->user(),
            AuditAction::Download,
            $inventory,
            null,
            null,
            'Unduh QR Code '.$inventory->inventory_number,
        );

        $filename = str_replace(['/', '\\'], '-', $inventory->inventory_number).'-qr.svg';

        return Storage::disk('public')->download($inventory->qr_code_path, $filename);
    }

    public function print(InventoryItem $inventory): View
    {
        $this->codeService->ensureCodes($inventory);
        $inventory->refresh()->load(['category', 'room.floor.building.campus', 'pic']);

        return view('inventory.print-label', compact('inventory'));
    }

    public function regenerate(InventoryItem $inventory): RedirectResponse
    {
        $this->codeService->generateAndSaveCodes($inventory);

        $this->auditLogService->log(
            auth()->user(),
            AuditAction::GenerateQr,
            $inventory,
            null,
            null,
            'Generate ulang QR Code '.$inventory->inventory_number,
        );

        return back()->with('success', 'QR Code berhasil digenerate ulang.');
    }

    public function labels(Request $request): Response
    {
        $request->validate([
            'inventory_ids' => ['required', 'array', 'min:1'],
            'inventory_ids.*' => ['integer', 'exists:inventory_items,id'],
        ]);

        $items = InventoryItem::query()
            ->whereIn('id', $request->input('inventory_ids'))
            ->get();

        foreach ($items as $item) {
            $this->codeService->ensureCodes($item);
        }

        $items = InventoryItem::query()
            ->whereIn('id', $request->input('inventory_ids'))
            ->get();

        $pdf = Pdf::loadView('exports.inventory-labels', compact('items'));

        return $pdf->download('inventory_labels_'.now()->format('Ymd_His').'.pdf');
    }

    /**
     * Mass print QR labels on A4 (configurable grid; default 5×9 = 45/page).
     * Does not replace inventory.labels or individual label print.
     */
    public function massLabels30(Request $request): Response|RedirectResponse
    {
        $request->validate([
            'scope' => ['required', 'in:selected,filter'],
            'inventory_ids' => ['required_if:scope,selected', 'array', 'min:1'],
            'inventory_ids.*' => ['integer', 'exists:inventory_items,id'],
        ]);

        $scope = (string) $request->input('scope');
        $filters = $this->filterService->extractFilters($request);
        $layout = $this->massLabelService->layout();
        $user = $request->user();

        Log::info('Mass label request received', [
            'scope' => $scope,
            'selected_count' => is_array($request->input('inventory_ids')) ? count($request->input('inventory_ids')) : 0,
            'filters' => collect($filters)->filter(fn ($v) => filled($v))->except(['include_trash', 'only_trash', 'warranty_days'])->all(),
            'layout' => [
                'columns' => $layout['columns'],
                'rows' => $layout['rows'],
                'per_page' => $layout['per_page'],
            ],
            'user_id' => $user?->id,
        ]);

        // Critical: release session lock BEFORE heavy PDF work.
        // Otherwise DataTables AJAX on the parent tab waits on the same session file → tn/7 Ajax error.
        $this->releaseSessionLock();

        try {
            @ini_set('memory_limit', '512M');
            @set_time_limit(180);

            $ids = $this->massLabelService->resolveIds($request);

            if ($ids === []) {
                Log::warning('Mass label aborted: no inventory matched', ['scope' => $scope]);

                return redirect()
                    ->route('inventory.items')
                    ->with('error', $scope === 'selected'
                        ? 'Tidak ada inventaris yang dipilih.'
                        : 'Tidak ada inventaris untuk dicetak. Sesuaikan filter terlebih dahulu.');
            }

            $labels = $this->massLabelService->buildLabels($ids);

            Log::info('Mass label inventory prepared', [
                'scope' => $scope,
                'requested_ids' => count($ids),
                'printable_count' => $labels->count(),
                'per_page' => $layout['per_page'],
                'pages' => (int) ceil(max(1, $labels->count()) / max(1, $layout['per_page'])),
            ]);

            if ($labels->isEmpty()) {
                return redirect()
                    ->route('inventory.items')
                    ->with('error', 'Tidak ada inventaris dengan nomor/QR valid untuk dicetak.');
            }

            $this->auditLogService->log(
                $user,
                AuditAction::Export,
                null,
                null,
                [
                    'format' => 'labels-mass',
                    'count' => $labels->count(),
                    'scope' => $scope,
                    'per_page' => $layout['per_page'],
                    'columns' => $layout['columns'],
                    'rows' => $layout['rows'],
                ],
                'Cetak Label QR Massal ('.$labels->count().' item, '.$layout['per_page'].'/halaman)',
            );

            Log::info('Mass label generating PDF', [
                'count' => $labels->count(),
                'per_page' => $layout['per_page'],
            ]);

            $response = $this->massLabelService->downloadPdf($labels);

            Log::info('Mass label PDF download ready', [
                'count' => $labels->count(),
                'per_page' => $layout['per_page'],
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('Mass label PDF failed', [
                'scope' => $scope,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()
                ->route('inventory.items')
                ->with('error', 'Gagal membuat label. Silakan hubungi Administrator.');
        }
    }

    /**
     * Allow concurrent AJAX (DataTables) while this request generates PDF.
     */
    protected function releaseSessionLock(): void
    {
        try {
            if (session()->isStarted()) {
                session()->save();
            }
        } catch (\Throwable) {
            // ignore
        }

        if (function_exists('session_write_close')) {
            @session_write_close();
        }
    }
}
