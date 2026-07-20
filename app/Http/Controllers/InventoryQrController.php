<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InventoryQrController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function scan(string $inventoryNumber): View
    {
        $inventory = InventoryItem::query()
            ->where(function ($query) use ($inventoryNumber) {
                $query->where('inventory_number', $inventoryNumber)
                    ->orWhere('barcode', $inventoryNumber);
            })
            ->with(['category', 'subCategory', 'room.floor.building.campus', 'orgUnit', 'photos'])
            ->firstOrFail();

        if (auth()->check()) {
            $this->auditLogService->log(auth()->user(), AuditAction::ScanQr, $inventory, null, null, "Scan QR {$inventoryNumber}");
        }

        return view('inventory.scan', compact('inventory'));
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

        $pdf = Pdf::loadView('exports.inventory-labels', compact('items'));

        return $pdf->download('inventory_labels_'.now()->format('Ymd_His').'.pdf');
    }
}
