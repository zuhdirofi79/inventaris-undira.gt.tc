<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use App\Services\InventoryCodeService;
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
        $inventory->refresh()->load(['category', 'room.floor.building.campus']);

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
}
