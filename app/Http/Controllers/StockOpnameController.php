<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\StockOpnameStatus;
use App\Http\Requests\ScanStockOpnameRequest;
use App\Http\Requests\StoreStockOpnameRequest;
use App\Models\Campus;
use App\Models\InventoryItem;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockOpnameController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
        protected NotificationService $notifications,
    ) {}

    public function index(): View
    {
        $stockOpnames = StockOpname::query()
            ->with(['campus', 'creator'])
            ->withCount('items')
            ->latest()
            ->paginate(20);

        return view('operations.stock-opnames.index', compact('stockOpnames'));
    }

    public function create(): View
    {
        return view('operations.stock-opnames.create', [
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreStockOpnameRequest $request): RedirectResponse
    {
        $stockOpname = StockOpname::query()->create([
            ...$request->validated(),
            'status' => StockOpnameStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $stockOpname,
            null,
            [
                'title' => $stockOpname->title,
                'campus_id' => $stockOpname->campus_id,
                'status' => $stockOpname->status->value,
            ],
            'STOCK_OPNAME_CREATED',
        );

        return redirect()->route('stock-opnames.show', $stockOpname)->with('success', 'Stock opname berhasil dibuat.');
    }

    public function show(Request $request, StockOpname $stockOpname): View
    {
        $stockOpname->load(['campus', 'creator']);

        $agg = $stockOpname->items()
            ->toBase()
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('SUM(CASE WHEN scanned_at IS NOT NULL THEN 1 ELSE 0 END) as total_scanned')
            ->first();

        $stats = [
            'total_scanned' => (int) ($agg->total_scanned ?? 0),
            'total_items' => (int) ($agg->total_items ?? 0),
        ];

        $items = $stockOpname->items()
            ->with(['inventoryItem:id,inventory_number,name,category_id', 'inventoryItem.category:id,name'])
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('operations.stock-opnames.show', compact('stockOpname', 'stats', 'items'));
    }

    public function start(StockOpname $stockOpname): RedirectResponse
    {
        $result = DB::transaction(function () use ($stockOpname) {
            $locked = StockOpname::query()->lockForUpdate()->findOrFail($stockOpname->id);

            if (! $locked->canStart()) {
                return [
                    'ok' => false,
                    'message' => 'Stock opname hanya dapat dimulai dari status Draft.',
                ];
            }

            $oldStatus = $locked->status->value;

            $locked->update([
                'status' => StockOpnameStatus::InProgress,
                'started_at' => now(),
            ]);

            $this->audit->log(
                request()->user(),
                AuditAction::Update,
                $locked,
                ['status' => $oldStatus],
                ['status' => StockOpnameStatus::InProgress->value, 'started_at' => $locked->started_at?->toISOString()],
                'STOCK_OPNAME_STARTED',
            );

            return ['ok' => true, 'opname' => $locked];
        });

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        $this->notifications->notifyStockOpnameStatus($result['opname'], 'started');

        return back()->with('success', 'Stock opname dimulai.');
    }

    public function complete(StockOpname $stockOpname): RedirectResponse
    {
        $result = DB::transaction(function () use ($stockOpname) {
            $locked = StockOpname::query()->lockForUpdate()->findOrFail($stockOpname->id);

            if (! $locked->canComplete()) {
                return [
                    'ok' => false,
                    'message' => 'Stock opname hanya dapat diselesaikan saat status Sedang Berlangsung.',
                ];
            }

            $oldStatus = $locked->status->value;

            $locked->update([
                'status' => StockOpnameStatus::Completed,
                'completed_at' => now(),
            ]);

            $this->audit->log(
                request()->user(),
                AuditAction::Update,
                $locked,
                ['status' => $oldStatus],
                ['status' => StockOpnameStatus::Completed->value, 'completed_at' => $locked->completed_at?->toISOString()],
                'STOCK_OPNAME_COMPLETED',
            );

            return ['ok' => true, 'opname' => $locked];
        });

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        $this->notifications->notifyStockOpnameStatus($result['opname'], 'completed');

        return back()->with('success', 'Stock opname selesai.');
    }

    public function scan(ScanStockOpnameRequest $request, StockOpname $stockOpname): JsonResponse
    {
        if (! $stockOpname->canScan()) {
            return response()->json([
                'message' => 'Scan hanya diperbolehkan saat stock opname sedang berlangsung.',
            ], 422);
        }

        $item = InventoryItem::query()
            ->where('inventory_number', $request->validated('inventory_number'))
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Inventaris tidak ditemukan.'], 404);
        }

        $opnameItem = DB::transaction(function () use ($stockOpname, $item) {
            $locked = StockOpname::query()->lockForUpdate()->findOrFail($stockOpname->id);

            if (! $locked->canScan()) {
                return null;
            }

            $opnameItem = StockOpnameItem::query()->firstOrCreate(
                [
                    'stock_opname_id' => $locked->id,
                    'inventory_item_id' => $item->id,
                ],
                ['status' => 'found']
            );

            $opnameItem->update(['scanned_at' => now()]);

            return $opnameItem->fresh();
        });

        if (! $opnameItem) {
            return response()->json([
                'message' => 'Scan hanya diperbolehkan saat stock opname sedang berlangsung.',
            ], 422);
        }

        return response()->json([
            'message' => 'Berhasil discan.',
            'item' => $item->load('category'),
        ]);
    }

    public function destroy(StockOpname $stockOpname): RedirectResponse
    {
        $result = DB::transaction(function () use ($stockOpname) {
            $locked = StockOpname::query()->lockForUpdate()->findOrFail($stockOpname->id);

            if (! $locked->canDelete()) {
                return [
                    'ok' => false,
                    'message' => 'Stock opname yang sudah selesai tidak dapat dihapus.',
                ];
            }

            $snapshot = [
                'title' => $locked->title,
                'campus_id' => $locked->campus_id,
                'status' => $locked->status->value,
            ];

            $locked->delete();

            $this->audit->log(
                request()->user(),
                AuditAction::Delete,
                null,
                $snapshot,
                null,
                'STOCK_OPNAME_DELETED',
            );

            return ['ok' => true];
        });

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('stock-opnames.index')->with('success', 'Stock opname berhasil dihapus.');
    }
}
