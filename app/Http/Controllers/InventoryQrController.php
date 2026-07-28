<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use App\Services\InventoryCodeService;
use App\Services\InventoryFilterService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryQrController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected InventoryCodeService $codeService,
        protected InventoryFilterService $filterService,
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
     * New feature: mass print 30 QR labels per A4 page (3×10).
     * Does not replace inventory.labels or individual label print.
     */
    public function massLabels30(Request $request): Response
    {
        $request->validate([
            'scope' => ['required', 'in:selected,filter'],
            'inventory_ids' => ['required_if:scope,selected', 'array', 'min:1'],
            'inventory_ids.*' => ['integer', 'exists:inventory_items,id'],
        ]);

        if ($request->input('scope') === 'selected') {
            $ids = collect($request->input('inventory_ids', []))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        } else {
            $ids = $this->filterService
                ->apply($request)
                ->orderBy('inventory_number')
                ->limit(500)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        abort_if($ids === [], 422, 'Tidak ada inventaris untuk dicetak. Pilih item atau sesuaikan filter.');

        $items = InventoryItem::query()
            ->whereIn('id', $ids)
            ->with([
                'category:id,name',
                'subCategory:id,name',
                'room:id,code,name,floor_id',
            ])
            ->orderBy('inventory_number')
            ->get();

        foreach ($items as $item) {
            $this->codeService->ensureCodes($item);
        }

        $items = InventoryItem::query()
            ->whereIn('id', $ids)
            ->with([
                'category:id,name',
                'subCategory:id,name',
                'room:id,code,name,floor_id',
            ])
            ->orderBy('inventory_number')
            ->get();

        $logoPath = public_path('images/undira.png');
        $logoSrc = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $this->auditLogService->log(
            $request->user(),
            AuditAction::Export,
            null,
            null,
            ['format' => 'labels-30', 'count' => $items->count(), 'scope' => $request->input('scope')],
            'Print Barcode Massal 30 Label ('.$items->count().' item)',
        );

        $pdf = Pdf::loadView('exports.inventory-labels-30', [
            'items' => $items,
            'logoSrc' => $logoSrc,
        ]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('inventory_labels_30_'.now()->format('Ymd_His').'.pdf');
    }
}
