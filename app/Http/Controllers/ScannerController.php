<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\InventoryItem;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScannerController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function index(): View
    {
        return view('operations.scanner.index');
    }

    public function lookup(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $code = trim($data['code']);

        $item = InventoryItem::query()
            ->where(function ($query) use ($code) {
                $query->where('inventory_number', $code)
                    ->orWhere('barcode', $code);
            })
            ->first();

        if (! $item) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Inventaris tidak ditemukan.', 'found' => false], 404);
            }

            return back()->with('error', 'Inventaris tidak ditemukan: '.$code);
        }

        $this->auditLogService->log(
            $request->user(),
            AuditAction::ScanQr,
            $item,
            null,
            ['code' => $code, 'affected_count' => 1],
            "Scan barcode/QR {$code}",
            reason: 'scanner_operations',
            affectedCount: 1,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'found' => true,
                'redirect' => route('inventory.show', $item),
                'item' => [
                    'id' => $item->id,
                    'inventory_number' => $item->inventory_number,
                    'name' => $item->name,
                ],
            ]);
        }

        return redirect()->route('inventory.show', $item);
    }
}
