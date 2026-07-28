<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\BorrowingStatus;
use App\Http\Requests\ApproveBorrowingRequest;
use App\Http\Requests\RejectBorrowingRequest;
use App\Http\Requests\ReturnBorrowingItemsRequest;
use App\Http\Requests\StoreBorrowingRequest;
use App\Http\Requests\UpdateBorrowingRequest;
use App\Models\Borrowing;
use App\Services\AuditLogService;
use App\Services\BorrowingApprovalService;
use App\Services\BorrowingReceiptService;
use App\Services\InventorySearchService;
use App\Support\InventarisCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BorrowingController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
        protected InventorySearchService $inventorySearch,
        protected BorrowingApprovalService $approval,
        protected BorrowingReceiptService $receipts,
    ) {}

    public function index(Request $request): View
    {
        $statusFilter = $request->input('status');

        $borrowings = Borrowing::query()
            ->with([
                'inventoryItem.category',
                'inventoryItem.room',
                'items.inventoryItem',
                'approvedBy',
                'requestedBy',
            ])
            ->withCount('items')
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('operations.borrowings.index', [
            'borrowings' => $borrowings,
            'statusFilter' => $statusFilter,
            'statusOptions' => BorrowingStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $selectedId = $request->integer('inventory_item_id') ?: (int) old('inventory_item_id');
        $selectedDetail = $selectedId > 0 ? $this->inventorySearch->detail($selectedId) : null;

        if ($selectedDetail && ($selectedDetail['borrowable'] ?? false) !== true) {
            $selectedDetail = null;
        }

        return view('operations.borrowings.create', [
            'selectedDetail' => $selectedDetail,
        ]);
    }

    public function store(StoreBorrowingRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['status']);

        $borrowing = $this->approval->submit($data, $request->user());

        $this->audit->log(
            $request->user(),
            AuditAction::Create,
            $borrowing,
            null,
            [
                'inventory_item_id' => $borrowing->inventory_item_id,
                'item_ids' => $borrowing->items->pluck('inventory_item_id')->all(),
                'items_count' => $borrowing->items->count(),
                'borrower_name' => $borrowing->borrower_name,
                'borrow_date' => $borrowing->borrow_date,
                'expected_return_date' => $borrowing->expected_return_date,
                'status' => $borrowing->status,
                'requested_by' => $borrowing->requested_by,
            ],
            'BORROWING_CREATED',
            "{$request->user()->name} membuat peminjaman ({$borrowing->items->count()} item).",
        );

        return redirect()->route('borrowings.index')
            ->with('success', 'Peminjaman berhasil diajukan dan menunggu persetujuan.');
    }

    public function show(Borrowing $borrowing): View
    {
        $borrowing->load([
            'items.inventoryItem.category',
            'items.inventoryItem.subCategory',
            'items.inventoryItem.room.floor.building.campus',
            'inventoryItem.category',
            'inventoryItem.subCategory',
            'inventoryItem.room.floor.building.campus',
            'approvedBy',
            'requestedBy',
        ]);

        return view('operations.borrowings.show', [
            'borrowing' => $borrowing,
            'qrDataUri' => $borrowing->approval_number ? $this->receipts->qrDataUri($borrowing) : null,
            'approvalUrl' => $this->receipts->approvalPublicUrl($borrowing),
        ]);
    }

    public function edit(Borrowing $borrowing): View
    {
        $borrowing->load(['items.inventoryItem']);

        return view('operations.borrowings.edit', compact('borrowing'));
    }

    public function update(UpdateBorrowingRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $old = $borrowing->only(['status', 'return_date', 'expected_return_date', 'borrower_name']);
        $wasReturned = false;
        $validated = $request->validated();
        $wantsFullReturn = ($validated['status'] ?? null) === BorrowingStatus::Dikembalikan
            || ($validated['status'] ?? null) === BorrowingStatus::Dikembalikan->value;

        DB::transaction(function () use ($borrowing, $validated, $wantsFullReturn, &$wasReturned) {
            if ($wantsFullReturn && $borrowing->status?->isActiveLoan()) {
                unset($validated['status']);
                if ($validated !== []) {
                    $borrowing->update($validated);
                }
                $this->approval->returnItems($borrowing->fresh(), null);
                $wasReturned = true;

                return;
            }

            $borrowing->update($validated);
        });

        $borrowing->refresh();
        InventarisCache::flush();

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            $old,
            $borrowing->only(['status', 'return_date', 'expected_return_date', 'borrower_name']),
            $wasReturned ? 'BORROWING_RETURNED' : 'BORROWING_UPDATED',
            $wasReturned
                ? "{$request->user()->name} mencatat pengembalian barang."
                : null,
        );

        return redirect()->route('borrowings.show', $borrowing)->with('success', 'Peminjaman berhasil diperbarui.');
    }

    public function returnItems(ReturnBorrowingItemsRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $itemIds = $request->input('return_all')
            ? null
            : array_map('intval', (array) $request->input('item_ids', []));

        $borrowing = $this->approval->returnItems($borrowing, $itemIds);

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            null,
            [
                'status' => $borrowing->status,
                'returned_item_ids' => $itemIds,
                'return_all' => (bool) $request->input('return_all'),
            ],
            'BORROWING_RETURNED',
            "{$request->user()->name} mengembalikan barang peminjaman.",
        );

        $message = $borrowing->status === BorrowingStatus::Dikembalikan
            ? 'Semua barang telah dikembalikan. Transaksi selesai.'
            : 'Pengembalian sebagian berhasil dicatat.';

        return redirect()->route('borrowings.show', $borrowing)->with('success', $message);
    }

    public function approve(ApproveBorrowingRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $old = $borrowing->only(['status', 'approved_by', 'approved_at', 'approval_number']);

        $borrowing = $this->approval->approve(
            $borrowing,
            $request->user(),
            $request->input('approval_notes'),
        );

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            $old,
            $borrowing->only(['status', 'approved_by', 'approved_at', 'approval_notes', 'approval_number']),
            'BORROWING_APPROVED',
            "{$request->user()->name} menyetujui peminjaman {$borrowing->approval_number}.",
        );

        return back()->with('success', 'Peminjaman berhasil disetujui.');
    }

    public function reject(RejectBorrowingRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $old = $borrowing->only(['status', 'approved_by', 'rejected_at', 'rejection_reason']);

        $borrowing = $this->approval->reject(
            $borrowing,
            $request->user(),
            (string) $request->input('rejection_reason'),
        );

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            $old,
            $borrowing->only(['status', 'approved_by', 'rejected_at', 'rejection_reason']),
            'BORROWING_REJECTED',
            "{$request->user()->name} menolak peminjaman. Alasan: ".$request->input('rejection_reason'),
        );

        return back()->with('success', 'Peminjaman berhasil ditolak.');
    }

    public function receipt(Borrowing $borrowing): View|RedirectResponse
    {
        abort_unless($borrowing->approval_number, 404);

        return view('operations.borrowings.receipt', [
            ...$this->receipts->receiptPayload($borrowing),
            'isPdf' => false,
            'autoPrint' => false,
        ]);
    }

    public function receiptPdf(Borrowing $borrowing): Response
    {
        abort_unless($borrowing->approval_number, 404);

        $payload = $this->receipts->receiptPayload($borrowing);
        $payload['isPdf'] = true;
        $payload['autoPrint'] = false;

        return Pdf::loadView('operations.borrowings.receipt', $payload)
            ->setPaper('a4', 'portrait')
            ->download('receipt_'.$borrowing->approval_number.'.pdf');
    }

    public function destroy(Borrowing $borrowing): RedirectResponse
    {
        $snapshot = [
            'inventory_item_id' => $borrowing->inventory_item_id,
            'item_ids' => $borrowing->items()->pluck('inventory_item_id')->all(),
            'borrower_name' => $borrowing->borrower_name,
            'status' => $borrowing->status,
            'approval_number' => $borrowing->approval_number,
        ];

        DB::transaction(function () use ($borrowing) {
            $this->approval->restoreInventoryOnDelete($borrowing);
            $borrowing->delete();
        });

        InventarisCache::flush();

        $this->audit->log(
            request()->user(),
            AuditAction::Delete,
            null,
            $snapshot,
            null,
            'BORROWING_DELETED',
        );

        return back()->with('success', 'Data peminjaman berhasil dihapus.');
    }
}
