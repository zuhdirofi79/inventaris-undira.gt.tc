<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\BorrowingStatus;
use App\Http\Requests\ApproveBorrowingItemRequest;
use App\Http\Requests\ApproveBorrowingRequest;
use App\Http\Requests\HandoverBorrowingItemRequest;
use App\Http\Requests\RejectBorrowingItemRequest;
use App\Http\Requests\RejectBorrowingRequest;
use App\Http\Requests\ReturnBorrowingItemsRequest;
use App\Http\Requests\SaveBorrowingItemApprovalsRequest;
use App\Http\Requests\StoreBorrowingRequest;
use App\Http\Requests\UpdateBorrowingRequest;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Campus;
use App\Policies\BorrowingPolicy;
use Carbon\Carbon;
use App\Services\AuditLogService;
use App\Services\BorrowingApprovalService;
use App\Services\BorrowingReceiptService;
use App\Services\BorrowingSupportingDocumentService;
use App\Services\InventorySearchService;
use App\Support\InventarisCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BorrowingController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
        protected InventorySearchService $inventorySearch,
        protected BorrowingApprovalService $approval,
        protected BorrowingReceiptService $receipts,
        protected BorrowingSupportingDocumentService $supportingDocuments,
    ) {}

    public function index(Request $request): View
    {
        $statusFilter = $this->resolveStatusFilter($request);
        $campusId = $this->resolveCampusFilter($request);
        $user = $request->user();

        $borrowings = Borrowing::query()
            ->with([
                'inventoryItem.category',
                'inventoryItem.room.floor.building.campus:id,name',
                'items.inventoryItem.room.floor.building.campus:id,name',
                'approvedBy',
                'requestedBy',
            ])
            ->withCount('items')
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($campusId === 0, fn ($q) => $q->whereRaw('0 = 1'))
            ->when($campusId, function ($q) use ($campusId) {
                // Match via line items, or legacy header inventory when no lines exist.
                // Multi-campus transactions appear if ANY item is on the selected campus.
                $q->where(function ($inner) use ($campusId) {
                    $inner->whereHas('items.inventoryItem', fn ($item) => $item->forCampus($campusId))
                        ->orWhere(function ($legacy) use ($campusId) {
                            $legacy->whereDoesntHave('items')
                                ->whereHas('inventoryItem', fn ($item) => $item->forCampus($campusId));
                        });
                });
            })
            // Viewer: hanya transaksi yang requested_by-nya user ini. Bukan filter nama/email.
            ->when(
                $user && app(BorrowingPolicy::class)->limitsToOwnRecords($user),
                fn ($q) => $q->where('requested_by', $user->id)
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('operations.borrowings.index', [
            'borrowings' => $borrowings,
            'statusFilter' => $statusFilter,
            'campusFilter' => $campusId && $campusId > 0 ? $campusId : null,
            'campusFilterActive' => $request->filled('campus_id'),
            'statusOptions' => BorrowingStatus::cases(),
            'campuses' => $this->campusDropdownOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Borrowing::class);
        view()->share('inlineValidationFields', ['supporting_document']);

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
        $data = $request->safe()->except(['status', 'supporting_document']);

        $borrowing = $this->approval->submit(
            $data,
            $request->user(),
            $request->file('supporting_document'),
        );

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
                'has_supporting_document' => $borrowing->hasSupportingDocument(),
            ],
            'BORROWING_CREATED',
            "{$request->user()->name} membuat peminjaman ({$borrowing->items->count()} item).",
        );

        return redirect()->route('borrowings.index')
            ->with('success', 'Peminjaman berhasil diajukan dan menunggu persetujuan.');
    }

    public function show(Borrowing $borrowing): View
    {
        $this->authorize('view', $borrowing);

        $borrowing->load([
            'items.inventoryItem.category',
            'items.inventoryItem.subCategory',
            'items.inventoryItem.room.floor.building.campus:id,name',
            'inventoryItem.category',
            'inventoryItem.subCategory',
            'inventoryItem.room.floor.building.campus:id,name',
            'approvedBy',
            'requestedBy',
        ]);

        return view('operations.borrowings.show', [
            'borrowing' => $borrowing,
            'campusNames' => $borrowing->campusNames(),
            'campusSummary' => $borrowing->campusSummary(),
            'qrDataUri' => $borrowing->approval_number ? $this->receipts->qrDataUri($borrowing) : null,
            'approvalUrl' => $this->receipts->approvalPublicUrl($borrowing),
        ]);
    }

    public function edit(Borrowing $borrowing): View
    {
        $borrowing->load(['items.inventoryItem']);
        view()->share('inlineValidationFields', ['supporting_document']);

        return view('operations.borrowings.edit', compact('borrowing'));
    }

    public function update(UpdateBorrowingRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $old = $borrowing->only(['status', 'return_date', 'expected_return_date', 'borrower_name']);
        $wasReturned = false;
        $validated = $request->safe()->except(['supporting_document', 'remove_supporting_document']);
        $wantsFullReturn = ($validated['status'] ?? null) === BorrowingStatus::Dikembalikan
            || ($validated['status'] ?? null) === BorrowingStatus::Dikembalikan->value;
        $uploadedFile = $request->file('supporting_document');
        $removeDocument = $request->boolean('remove_supporting_document') && ! $uploadedFile;
        $newDocPath = null;
        $oldDocPath = $borrowing->supporting_document_path;
        $storingDocument = false;

        try {
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

            if ($uploadedFile) {
                $storingDocument = true;
                $meta = $this->supportingDocuments->store($borrowing, $uploadedFile);
                $newDocPath = $meta['supporting_document_path'];
                $borrowing->update($meta);
                $storingDocument = false;
                if (is_string($oldDocPath) && $oldDocPath !== '' && $oldDocPath !== $newDocPath) {
                    try {
                        $this->supportingDocuments->deletePath($oldDocPath);
                    } catch (\Throwable $cleanup) {
                        Log::warning('Dokumen pendukung lama tidak terhapus.', [
                            'exception' => $cleanup::class,
                        ]);
                    }
                }
            } elseif ($removeDocument && $borrowing->hasSupportingDocument()) {
                $this->supportingDocuments->clear($borrowing);
            }
        } catch (\Throwable $e) {
            if ($storingDocument && is_string($newDocPath) && $newDocPath !== '' && $borrowing->fresh()?->supporting_document_path !== $newDocPath) {
                $this->supportingDocuments->deletePath($newDocPath);
            }

            if ($storingDocument) {
                Log::warning('Dokumen pendukung peminjaman gagal disimpan.', [
                    'exception' => $e::class,
                ]);

                throw ValidationException::withMessages([
                    'supporting_document' => 'Dokumen tidak dapat disimpan saat ini. Silakan coba kembali.',
                ]);
            }

            throw $e;
        }

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

    public function downloadSupportingDocument(Borrowing $borrowing): StreamedResponse
    {
        $this->authorize('view', $borrowing);
        abort_unless($this->supportingDocuments->exists($borrowing), 404);

        return $this->supportingDocuments->stream($borrowing, download: true);
    }

    public function viewSupportingDocument(Borrowing $borrowing): StreamedResponse
    {
        $this->authorize('view', $borrowing);
        abort_unless($this->supportingDocuments->exists($borrowing), 404);

        return $this->supportingDocuments->stream($borrowing, download: false);
    }

    public function returnItems(ReturnBorrowingItemsRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $itemIds = $request->input('return_all')
            ? null
            : array_map('intval', (array) $request->input('item_ids', []));

        $returnedAt = $request->filled('returned_at')
            ? Carbon::parse((string) $request->input('returned_at'))
            : null;

        $borrowing = $this->approval->returnItems($borrowing, $itemIds, $returnedAt);

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            null,
            [
                'status' => $borrowing->status,
                'returned_item_ids' => $itemIds,
                'return_all' => (bool) $request->input('return_all'),
                'returned_at' => $returnedAt?->toDateTimeString(),
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
        return back()->with(
            'error',
            'Persetujuan dilakukan per inventaris pada halaman detail peminjaman, lalu disimpan dengan Simpan Approval.',
        );
    }

    public function reject(RejectBorrowingRequest $request, Borrowing $borrowing): RedirectResponse
    {
        return back()->with(
            'error',
            'Penolakan dilakukan per inventaris pada halaman detail peminjaman, lalu disimpan dengan Simpan Approval.',
        );
    }

    public function saveApprovals(SaveBorrowingItemApprovalsRequest $request, Borrowing $borrowing): RedirectResponse
    {
        $old = $borrowing->only(['status', 'approved_by', 'approved_at', 'rejected_at', 'approval_number']);

        $borrowing = $this->approval->saveItemDecisions(
            $borrowing,
            $request->user(),
            $request->decisionsPayload(),
        );

        $counts = $borrowing->itemDecisionCounts();

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            $old,
            [
                'status' => $borrowing->status?->value,
                'approved_by' => $borrowing->approved_by,
                'approved_at' => $borrowing->approved_at,
                'rejected_at' => $borrowing->rejected_at,
                'approval_number' => $borrowing->approval_number,
                'approved_count' => $counts['approved'],
                'rejected_count' => $counts['rejected'],
            ],
            'BORROWING_ITEMS_APPROVED_BATCH',
            "{$request->user()->name} menyimpan approval peminjaman #{$borrowing->id} "
                ."({$counts['approved']} disetujui, {$counts['rejected']} ditolak).",
        );

        $message = match ($borrowing->status) {
            BorrowingStatus::Ditolak => 'Approval disimpan. Seluruh pengajuan ditolak.',
            BorrowingStatus::Disetujui => 'Approval disimpan. Persetujuan transaksi selesai.',
            default => 'Approval disimpan.',
        };

        return redirect()->route('borrowings.show', $borrowing)->with('success', $message);
    }

    public function approveItem(
        ApproveBorrowingItemRequest $request,
        Borrowing $borrowing,
        BorrowingItem $borrowingItem,
    ): RedirectResponse {
        $oldStatus = $borrowingItem->status?->value;

        $borrowing = $this->approval->approveItem($borrowing, $borrowingItem, $request->user());

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            ['item_id' => $borrowingItem->id, 'status' => $oldStatus],
            ['item_id' => $borrowingItem->id, 'status' => 'disetujui', 'borrowing_status' => $borrowing->status?->value],
            'BORROWING_ITEM_APPROVED',
            "{$request->user()->name} menyetujui item #{$borrowingItem->id} pada peminjaman #{$borrowing->id}.",
        );

        $message = $borrowing->status === BorrowingStatus::MenungguPersetujuan
            ? 'Inventaris disetujui. Masih ada item yang menunggu persetujuan.'
            : 'Inventaris disetujui. Persetujuan transaksi selesai.';

        return back()->with('success', $message);
    }

    public function rejectItem(
        RejectBorrowingItemRequest $request,
        Borrowing $borrowing,
        BorrowingItem $borrowingItem,
    ): RedirectResponse {
        $oldStatus = $borrowingItem->status?->value;
        $reason = (string) $request->input('rejection_reason');

        $borrowing = $this->approval->rejectItem($borrowing, $borrowingItem, $request->user(), $reason);

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            ['item_id' => $borrowingItem->id, 'status' => $oldStatus],
            [
                'item_id' => $borrowingItem->id,
                'status' => 'ditolak',
                'rejection_reason' => $reason,
                'borrowing_status' => $borrowing->status?->value,
            ],
            'BORROWING_ITEM_REJECTED',
            "{$request->user()->name} menolak item #{$borrowingItem->id}. Alasan: {$reason}",
        );

        $message = $borrowing->status === BorrowingStatus::MenungguPersetujuan
            ? 'Inventaris ditolak. Masih ada item yang menunggu persetujuan.'
            : ($borrowing->status === BorrowingStatus::Ditolak
                ? 'Inventaris ditolak. Seluruh pengajuan ditolak.'
                : 'Inventaris ditolak. Persetujuan transaksi selesai (sebagian disetujui).');

        return back()->with('success', $message);
    }

    public function handoverItem(
        HandoverBorrowingItemRequest $request,
        Borrowing $borrowing,
        BorrowingItem $borrowingItem,
    ): RedirectResponse {
        $borrowedAt = $request->filled('borrowed_at')
            ? Carbon::parse((string) $request->input('borrowed_at'), (string) config('app.timezone'))
            : null;

        $borrowing = $this->approval->handoverItem(
            $borrowing,
            $borrowingItem,
            $request->user(),
            $borrowedAt,
        );

        $this->audit->log(
            $request->user(),
            AuditAction::Update,
            $borrowing,
            null,
            [
                'item_id' => $borrowingItem->id,
                'status' => 'dipinjam',
                'borrowed_at' => $borrowedAt?->toDateTimeString() ?? now()->toDateTimeString(),
            ],
            'BORROWING_ITEM_HANDED_OVER',
            "{$request->user()->name} menyerahkan item #{$borrowingItem->id} (tanggal pinjam aktual dicatat).",
        );

        return back()->with('success', 'Barang berhasil diserahkan. Status inventaris menjadi Dipinjam.');
    }

    public function receipt(Borrowing $borrowing): View|RedirectResponse
    {
        $this->authorize('view', $borrowing);
        abort_unless($borrowing->approval_number, 404);

        return view('operations.borrowings.receipt', [
            ...$this->receipts->receiptPayload($borrowing),
            'isPdf' => false,
            'autoPrint' => false,
        ]);
    }

    public function receiptPdf(Borrowing $borrowing): Response
    {
        $this->authorize('view', $borrowing);
        abort_unless($borrowing->approval_number, 404);

        $payload = $this->receipts->receiptPayload($borrowing);
        $payload['isPdf'] = true;
        $payload['autoPrint'] = false;

        return Pdf::loadView('operations.borrowings.receipt', $payload)
            ->setPaper('a4', 'portrait')
            ->download('receipt_'.$borrowing->approval_number.'.pdf');
    }

    /**
     * Dropdown kampus untuk /borrowings saja.
     * Tidak memakai cache key bersama `campuses.active.dropdown`
     * (bisa terisi pluck string dari modul lain → $campus->id on string).
     *
     * @return Collection<int, Campus>
     */
    protected function campusDropdownOptions(): Collection
    {
        return Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn ($campus) => $campus instanceof Campus && isset($campus->id));
    }

    protected function resolveCampusFilter(Request $request): ?int
    {
        if (! $request->filled('campus_id')) {
            return null;
        }

        $raw = $request->input('campus_id');
        if (! is_numeric($raw) || (int) $raw < 1) {
            return 0;
        }

        $id = (int) $raw;

        return Campus::query()->whereKey($id)->exists() ? $id : 0;
    }

    protected function resolveStatusFilter(Request $request): ?string
    {
        $status = trim((string) $request->input('status', ''));
        if ($status === '') {
            return null;
        }

        $allowed = array_map(fn (BorrowingStatus $case) => $case->value, BorrowingStatus::cases());

        return in_array($status, $allowed, true) ? $status : null;
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
