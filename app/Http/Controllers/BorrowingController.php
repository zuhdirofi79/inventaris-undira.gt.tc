<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\BorrowingStatus;
use App\Enums\InventoryStatus;
use App\Http\Requests\ApproveBorrowingRequest;
use App\Http\Requests\RejectBorrowingRequest;
use App\Http\Requests\StoreBorrowingRequest;
use App\Http\Requests\UpdateBorrowingRequest;
use App\Models\Borrowing;
use App\Models\InventoryItem;
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
            ->with(['inventoryItem.category', 'inventoryItem.room', 'approvedBy', 'requestedBy'])
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
            $borrowing->only([
                'inventory_item_id',
                'borrower_name',
                'borrow_date',
                'expected_return_date',
                'status',
                'requested_by',
            ]),
            'BORROWING_CREATED',
            "{$request->user()->name} membuat peminjaman.",
        );

        return redirect()->route('borrowings.index')
            ->with('success', 'Peminjaman berhasil diajukan dan menunggu persetujuan.');
    }

    public function show(Borrowing $borrowing): View
    {
        $borrowing->load([
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
        return view('operations.borrowings.edit', compact('borrowing'));
    }

    public function update(UpdateBorrowingRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $old = $borrowing->only(['status', 'return_date', 'expected_return_date', 'borrower_name']);
        $wasReturned = false;

        DB::transaction(function () use ($request, $borrowing, &$wasReturned) {
            $borrowing->update($request->validated());

            if ($borrowing->status === BorrowingStatus::Dikembalikan) {
                $wasReturned = true;
                $borrowing->update(['return_date' => $borrowing->return_date ?? now()]);
                InventoryItem::query()
                    ->where('id', $borrowing->inventory_item_id)
                    ->update(['status' => InventoryStatus::Aktif]);
            }
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
        $snapshot = $borrowing->only(['inventory_item_id', 'borrower_name', 'status', 'approval_number']);

        DB::transaction(function () use ($borrowing) {
            if ($borrowing->status?->isActiveLoan()) {
                InventoryItem::query()
                    ->where('id', $borrowing->inventory_item_id)
                    ->update(['status' => InventoryStatus::Aktif]);
            }

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
