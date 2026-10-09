<?php

namespace App\Services;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\InventoryStatus;
use App\Mail\BorrowingApprovedMail;
use App\Mail\BorrowingPendingAdminMail;
use App\Mail\BorrowingRejectedMail;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\InventoryItem;
use App\Models\User;
use App\Support\InventarisCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BorrowingApprovalService
{
    public function __construct(
        protected NotificationService $notifications,
        protected BorrowingApprovalNumberService $approvalNumbers,
        protected BorrowingItemResolver $itemResolver,
        protected BorrowingSupportingDocumentService $supportingDocuments,
    ) {}

    /**
     * Create a pending borrowing (header + items) without changing inventory status.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data, User $requester, ?\Illuminate\Http\UploadedFile $supportingDocument = null): Borrowing
    {
        $inventoryIds = $this->itemResolver->normalizeRequestItems($data);
        $header = collect($data)->except([
            'inventory_item_id',
            'inventory_item_ids',
            'items',
            'status',
            'supporting_document',
            'remove_supporting_document',
        ])->all();

        $storedPath = null;

        try {
            $borrowing = DB::transaction(function () use ($header, $inventoryIds, $requester) {
                $borrowing = Borrowing::query()->create([
                    ...$header,
                    // Keep first item on header for backward-compatible code paths.
                    'inventory_item_id' => $inventoryIds[0] ?? null,
                    'status' => BorrowingStatus::MenungguPersetujuan,
                    'requested_by' => $requester->id,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'approval_notes' => null,
                    'approval_number' => null,
                    'notification_sent' => false,
                    'supporting_document_path' => null,
                    'supporting_document_original_name' => null,
                    'supporting_document_size' => null,
                ]);

                foreach ($this->itemResolver->buildItemRows($inventoryIds) as $row) {
                    $borrowing->items()->create($row);
                }

                return $borrowing;
            });

            if ($supportingDocument) {
                $meta = $this->supportingDocuments->store($borrowing, $supportingDocument);
                $storedPath = $meta['supporting_document_path'];
                $borrowing->update($meta);
            }
        } catch (\Throwable $e) {
            if ($storedPath) {
                $this->supportingDocuments->deletePath($storedPath);
            }

            throw $e;
        }

        $borrowing->load(['items.inventoryItem', 'inventoryItem', 'requestedBy']);

        $this->notifications->notifyBorrowingCreated($borrowing);
        $this->notifyAdminsByEmail($borrowing);
        InventarisCache::flush();

        return $borrowing;
    }

    /**
     * Approve all remaining pending items (transaction-level convenience).
     * Inventory stays available until each item is handed over.
     */
    public function approve(Borrowing $borrowing, User $approver, ?string $notes = null): Borrowing
    {
        $this->assertPending($borrowing);
        $finalized = false;

        $borrowing = DB::transaction(function () use ($borrowing, $approver, $notes, &$finalized) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);
            $this->ensureItems($locked);

            $pendingIds = BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->where('status', BorrowingItemStatus::MenungguPersetujuan)
                ->pluck('inventory_item_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($pendingIds === []) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada inventaris yang masih menunggu persetujuan.',
                ]);
            }

            $this->assertInventoriesAvailable($pendingIds, (int) $locked->id);

            BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->where('status', BorrowingItemStatus::MenungguPersetujuan)
                ->update([
                    'status' => BorrowingItemStatus::Disetujui,
                    'rejection_reason' => null,
                ]);

            if ($notes !== null && $notes !== '') {
                $locked->update(['approval_notes' => $notes]);
            }

            $finalized = $this->finalizeHeaderIfReady($locked, $approver);

            return $locked->fresh($this->defaultRelations());
        });

        if ($finalized) {
            $this->notifyAfterFinalDecision($borrowing);
        }

        InventarisCache::flush();

        return $borrowing;
    }

    /**
     * Reject all remaining pending items (transaction-level convenience).
     */
    public function reject(Borrowing $borrowing, User $approver, string $reason): Borrowing
    {
        $this->assertPending($borrowing);
        $finalized = false;

        $borrowing = DB::transaction(function () use ($borrowing, $approver, $reason, &$finalized) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);
            $this->ensureItems($locked);

            $updated = BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->where('status', BorrowingItemStatus::MenungguPersetujuan)
                ->update([
                    'status' => BorrowingItemStatus::Ditolak,
                    'rejection_reason' => $reason,
                ]);

            if ($updated < 1) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada inventaris yang masih menunggu persetujuan.',
                ]);
            }

            $locked->update(['rejection_reason' => $reason]);

            $finalized = $this->finalizeHeaderIfReady($locked, $approver);

            return $locked->fresh($this->defaultRelations());
        });

        if ($finalized) {
            $this->notifyAfterFinalDecision($borrowing);
        }

        InventarisCache::flush();

        return $borrowing;
    }

    /**
     * Persist all pending item decisions in one transaction, then finalize the header.
     *
     * @param  list<array{id: int, action: string, rejection_reason?: string}>  $decisions
     */
    public function saveItemDecisions(Borrowing $borrowing, User $approver, array $decisions): Borrowing
    {
        $this->assertPending($borrowing);
        $finalized = false;

        $borrowing = DB::transaction(function () use ($borrowing, $approver, $decisions, &$finalized) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);
            $this->ensureItems($locked);

            $pending = BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->where('status', BorrowingItemStatus::MenungguPersetujuan)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($pending->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada inventaris yang masih menunggu persetujuan.',
                ]);
            }

            $ownedIds = BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $seen = [];
            foreach ($decisions as $row) {
                $decisionId = (int) ($row['id'] ?? 0);
                if (isset($seen[$decisionId])) {
                    throw ValidationException::withMessages([
                        'decisions' => 'Keputusan inventaris duplikat tidak diterima.',
                    ]);
                }
                $seen[$decisionId] = true;

                if (! in_array($decisionId, $ownedIds, true)) {
                    throw ValidationException::withMessages([
                        'decisions' => 'Inventaris tidak termasuk dalam transaksi ini.',
                    ]);
                }
            }

            $byId = collect($decisions)->keyBy(fn (array $row) => (int) ($row['id'] ?? 0));

            foreach ($pending as $id => $line) {
                $row = $byId->get($id);
                $action = is_array($row) ? ($row['action'] ?? null) : null;
                if (! in_array($action, ['approve', 'reject'], true)) {
                    throw ValidationException::withMessages([
                        'items' => 'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak untuk seluruh inventaris.',
                    ]);
                }
            }

            $approveInventoryIds = [];
            foreach ($pending as $id => $line) {
                $row = $byId->get($id);
                if (is_array($row) && ($row['action'] ?? null) === 'approve') {
                    $approveInventoryIds[] = (int) $line->inventory_item_id;
                }
            }

            if ($approveInventoryIds !== []) {
                $this->assertInventoriesAvailable($approveInventoryIds, (int) $locked->id);
            }

            foreach ($pending as $id => $line) {
                $row = $byId->get($id);
                if (($row['action'] ?? null) === 'approve') {
                    $line->update([
                        'status' => BorrowingItemStatus::Disetujui,
                        'rejection_reason' => null,
                    ]);

                    continue;
                }

                $reason = trim((string) ($row['rejection_reason'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages([
                        'decisions' => 'Alasan penolakan wajib diisi.',
                    ]);
                }

                $line->update([
                    'status' => BorrowingItemStatus::Ditolak,
                    'rejection_reason' => $reason,
                ]);
            }

            $finalized = $this->finalizeHeaderIfReady($locked, $approver);

            return $locked->fresh($this->defaultRelations());
        });

        if ($finalized) {
            $this->notifyAfterFinalDecision($borrowing);
        }

        InventarisCache::flush();

        return $borrowing;
    }

    public function approveItem(Borrowing $borrowing, BorrowingItem $item, User $approver): Borrowing
    {
        $this->assertItemBelongs($borrowing, $item);
        $this->assertPending($borrowing);
        $finalized = false;

        $borrowing = DB::transaction(function () use ($borrowing, $item, $approver, &$finalized) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);
            $line = BorrowingItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->assertItemBelongs($locked, $line);

            if ($line->status !== BorrowingItemStatus::MenungguPersetujuan) {
                throw ValidationException::withMessages([
                    'item' => 'Inventaris ini sudah diproses sebelumnya.',
                ]);
            }

            $this->assertInventoriesAvailable([(int) $line->inventory_item_id], (int) $locked->id);

            $line->update([
                'status' => BorrowingItemStatus::Disetujui,
                'rejection_reason' => null,
            ]);

            $finalized = $this->finalizeHeaderIfReady($locked, $approver);

            return $locked->fresh($this->defaultRelations());
        });

        if ($finalized) {
            $this->notifyAfterFinalDecision($borrowing);
        }

        InventarisCache::flush();

        return $borrowing;
    }

    public function rejectItem(Borrowing $borrowing, BorrowingItem $item, User $approver, string $reason): Borrowing
    {
        $this->assertItemBelongs($borrowing, $item);
        $this->assertPending($borrowing);
        $finalized = false;

        $borrowing = DB::transaction(function () use ($borrowing, $item, $approver, $reason, &$finalized) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);
            $line = BorrowingItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->assertItemBelongs($locked, $line);

            if ($line->status !== BorrowingItemStatus::MenungguPersetujuan) {
                throw ValidationException::withMessages([
                    'item' => 'Inventaris ini sudah diproses sebelumnya.',
                ]);
            }

            $line->update([
                'status' => BorrowingItemStatus::Ditolak,
                'rejection_reason' => $reason,
            ]);

            $finalized = $this->finalizeHeaderIfReady($locked, $approver);

            return $locked->fresh($this->defaultRelations());
        });

        if ($finalized) {
            $this->notifyAfterFinalDecision($borrowing);
        }

        InventarisCache::flush();

        return $borrowing;
    }

    /**
     * Physical handover: Disetujui → Dipinjam + borrowed_at + inventory.dipinjam.
     */
    public function handoverItem(
        Borrowing $borrowing,
        BorrowingItem $item,
        User $actor,
        ?CarbonInterface $borrowedAt = null,
    ): Borrowing {
        $this->assertItemBelongs($borrowing, $item);

        return DB::transaction(function () use ($borrowing, $item, $borrowedAt) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [
                BorrowingStatus::Disetujui,
                BorrowingStatus::Dipinjam,
                BorrowingStatus::Terlambat,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Serah terima hanya dapat dilakukan pada peminjaman yang sudah disetujui.',
                ]);
            }

            $line = BorrowingItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->assertItemBelongs($locked, $line);

            if ($line->status !== BorrowingItemStatus::Disetujui) {
                throw ValidationException::withMessages([
                    'item' => 'Hanya inventaris berstatus Disetujui yang dapat diserahkan.',
                ]);
            }

            $this->assertInventoriesAvailable([(int) $line->inventory_item_id], (int) $locked->id);

            $at = $borrowedAt ?? now();

            $claimed = BorrowingItem::query()
                ->whereKey($line->id)
                ->where('borrowing_id', $locked->id)
                ->where('status', BorrowingItemStatus::Disetujui->value)
                ->update(['status' => BorrowingItemStatus::Dipinjam->value]);

            if ($claimed !== 1) {
                throw ValidationException::withMessages([
                    'item' => 'Inventaris ini sudah diserahkan atau statusnya sudah berubah.',
                ]);
            }

            $handedOver = BorrowingItem::query()->whereKey($line->id)->firstOrFail();
            $handedOver->borrowed_at = $at;
            $handedOver->save();

            $inventoryUpdated = InventoryItem::query()
                ->whereKey($line->inventory_item_id)
                ->where('status', InventoryStatus::Aktif->value)
                ->update(['status' => InventoryStatus::Dipinjam->value]);

            if ($inventoryUpdated !== 1) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'Inventaris tidak dapat diserahkan karena status operasionalnya bukan Aktif.',
                ]);
            }

            if ($locked->status === BorrowingStatus::Disetujui) {
                $locked->update(['status' => BorrowingStatus::Dipinjam]);
            }

            InventarisCache::flush();

            return $locked->fresh($this->defaultRelations());
        });
    }

    /**
     * Return selected line items (or all open borrowed lines). Updates header when settled.
     *
     * @param  list<int>|null  $borrowingItemIds  null = return all borrowed items
     */
    public function returnItems(
        Borrowing $borrowing,
        ?array $borrowingItemIds = null,
        ?CarbonInterface $returnedAt = null,
    ): Borrowing {
        return DB::transaction(function () use ($borrowing, $borrowingItemIds, $returnedAt) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [
                BorrowingStatus::Disetujui,
                BorrowingStatus::Dipinjam,
                BorrowingStatus::Terlambat,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya peminjaman aktif yang dapat dikembalikan.',
                ]);
            }

            $this->ensureItems($locked);

            $query = BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->where('status', BorrowingItemStatus::Dipinjam)
                ->lockForUpdate();

            if ($borrowingItemIds !== null) {
                $query->whereIn('id', $borrowingItemIds);
            }

            $lines = $query->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada barang yang dapat dikembalikan.',
                ]);
            }

            $at = $returnedAt ?? now();
            foreach ($lines as $line) {
                $line->update([
                    'status' => BorrowingItemStatus::Dikembalikan,
                    'returned_at' => $at,
                ]);
            }

            InventoryItem::query()
                ->whereIn('id', $lines->pluck('inventory_item_id')->all())
                ->update(['status' => InventoryStatus::Aktif]);

            $locked->load('items');

            if ($locked->allItemsReturned()) {
                $locked->update([
                    'status' => BorrowingStatus::Dikembalikan,
                    'return_date' => $locked->return_date ?? $at->toDateString(),
                ]);
            }

            InventarisCache::flush();

            return $locked->fresh($this->defaultRelations());
        });
    }

    /**
     * Restore inventory when deleting an active loan.
     */
    public function restoreInventoryOnDelete(Borrowing $borrowing): void
    {
        if (! $borrowing->status?->isActiveLoan()) {
            return;
        }

        $ids = BorrowingItem::query()
            ->where('borrowing_id', $borrowing->id)
            ->where('status', BorrowingItemStatus::Dipinjam)
            ->pluck('inventory_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        InventoryItem::query()
            ->whereIn('id', $ids)
            ->where('status', InventoryStatus::Dipinjam)
            ->update(['status' => InventoryStatus::Aktif]);
    }

    /**
     * Finalize header when every line has left menunggu_persetujuan.
     * Returns true if header transitioned out of pending in this call.
     */
    protected function finalizeHeaderIfReady(Borrowing $locked, User $approver): bool
    {
        $locked->load('items');

        if ($locked->status !== BorrowingStatus::MenungguPersetujuan) {
            return false;
        }

        if ($locked->hasPendingItemApprovals()) {
            return false;
        }

        $counts = $locked->itemDecisionCounts();

        if ($counts['approved'] === 0) {
            $reason = BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->where('status', BorrowingItemStatus::Ditolak)
                ->whereNotNull('rejection_reason')
                ->value('rejection_reason')
                ?? $locked->rejection_reason
                ?? 'Semua inventaris ditolak.';

            $locked->update([
                'status' => BorrowingStatus::Ditolak,
                'approved_by' => $approver->id,
                'approved_at' => null,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return true;
        }

        $this->assertApprovalNumberImmutable($locked);
        $approvalNumber = $locked->approval_number ?: $this->approvalNumbers->generate();

        $notes = $locked->approval_notes;
        if ($counts['rejected'] > 0) {
            $summary = "{$counts['approved']} inventaris disetujui, {$counts['rejected']} inventaris ditolak.";
            $notes = filled($notes) ? trim($notes.' '.$summary) : $summary;
        }

        $locked->update([
            'status' => BorrowingStatus::Disetujui,
            'approval_number' => $approvalNumber,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejected_at' => null,
            'approval_notes' => $notes,
        ]);

        return true;
    }

    protected function notifyAfterFinalDecision(Borrowing $borrowing): void
    {
        $borrowing->loadMissing(['items.inventoryItem.category', 'inventoryItem', 'requestedBy', 'approvedBy']);

        if ($borrowing->status === BorrowingStatus::Ditolak) {
            $this->notifications->notifyBorrowingRejected($borrowing);
            $this->sendBorrowerMail($borrowing, new BorrowingRejectedMail($borrowing));

            return;
        }

        if ($borrowing->status === BorrowingStatus::Disetujui) {
            $this->notifications->notifyBorrowingApproved($borrowing);
            $this->sendBorrowerMail($borrowing, new BorrowingApprovedMail($borrowing));
        }
    }

    /**
     * @param  list<int>  $inventoryIds
     */
    protected function assertInventoriesAvailable(array $inventoryIds, int $exceptBorrowingId): void
    {
        if ($inventoryIds === []) {
            throw ValidationException::withMessages([
                'items' => 'Peminjaman tidak memiliki daftar barang.',
            ]);
        }

        $items = InventoryItem::query()
            ->whereIn('id', $inventoryIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($inventoryIds as $id) {
            $item = $items->get($id);
            if (! $item) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'Inventaris tidak ditemukan.',
                ]);
            }

            $status = $item->status instanceof InventoryStatus
                ? $item->status
                : InventoryStatus::tryFrom((string) $item->status);

            if ($status === InventoryStatus::Dipinjam) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => "Tidak dapat diproses karena {$item->inventory_number} sedang Dipinjam.",
                ]);
            }

            if (Borrowing::inventoryIsBlocked((int) $id, $exceptBorrowingId)) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => "Tidak dapat diproses karena {$item->inventory_number} sedang dalam transaksi peminjaman lain.",
                ]);
            }
        }
    }

    protected function assertPending(Borrowing $borrowing): void
    {
        if ($borrowing->status !== BorrowingStatus::MenungguPersetujuan) {
            throw ValidationException::withMessages([
                'status' => 'Peminjaman ini sudah diproses dan tidak dapat diapprove/reject ulang.',
            ]);
        }
    }

    protected function assertApprovalNumberImmutable(Borrowing $borrowing): void
    {
        if ($borrowing->approval_number && $borrowing->status !== BorrowingStatus::MenungguPersetujuan) {
            throw ValidationException::withMessages([
                'approval_number' => 'Nomor approval tidak boleh diubah.',
            ]);
        }
    }

    protected function assertItemBelongs(Borrowing $borrowing, BorrowingItem $item): void
    {
        if ((int) $item->borrowing_id !== (int) $borrowing->id) {
            throw ValidationException::withMessages([
                'item' => 'Inventaris tidak termasuk dalam transaksi ini.',
            ]);
        }
    }

    /**
     * Ensure line rows exist for legacy single-item borrowings.
     */
    protected function ensureItems(Borrowing $borrowing): void
    {
        if (BorrowingItem::query()->where('borrowing_id', $borrowing->id)->exists()) {
            return;
        }

        if (! $borrowing->inventory_item_id) {
            return;
        }

        $status = match ($borrowing->status) {
            BorrowingStatus::Dikembalikan => BorrowingItemStatus::Dikembalikan,
            BorrowingStatus::Ditolak => BorrowingItemStatus::Ditolak,
            BorrowingStatus::Disetujui => BorrowingItemStatus::Disetujui,
            BorrowingStatus::Dipinjam,
            BorrowingStatus::Terlambat => BorrowingItemStatus::Dipinjam,
            default => BorrowingItemStatus::MenungguPersetujuan,
        };

        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $borrowing->inventory_item_id,
            'qty' => 1,
            'status' => $status,
            'borrowed_at' => $status === BorrowingItemStatus::Dipinjam ? now() : null,
            'returned_at' => $status === BorrowingItemStatus::Dikembalikan ? now() : null,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function defaultRelations(): array
    {
        return [
            'items.inventoryItem.category',
            'items.inventoryItem.subCategory',
            'items.inventoryItem.room.floor.building.campus',
            'inventoryItem.category',
            'inventoryItem.subCategory',
            'inventoryItem.room',
            'approvedBy',
            'requestedBy',
        ];
    }

    protected function notifyAdminsByEmail(Borrowing $borrowing): void
    {
        $admins = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin', 'admin']))
            ->get();

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new BorrowingPendingAdminMail($borrowing, $admin));
            } catch (\Throwable $e) {
                Log::warning('Gagal mengirim email pengajuan peminjaman ke admin.', [
                    'admin_id' => $admin->id,
                    'borrowing_id' => $borrowing->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function sendBorrowerMail(Borrowing $borrowing, object $mailable): void
    {
        $email = trim((string) $borrowing->borrower_email);

        if ($email === '') {
            Log::info('Email notifikasi peminjaman tidak dikirim: alamat peminjam kosong.', [
                'borrowing_id' => $borrowing->id,
                'approval_number' => $borrowing->approval_number,
                'mailable' => $mailable::class,
            ]);

            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Log::info('Email notifikasi peminjaman tidak dikirim: alamat peminjam tidak valid.', [
                'borrowing_id' => $borrowing->id,
                'approval_number' => $borrowing->approval_number,
                'email' => $email,
                'mailable' => $mailable::class,
            ]);

            return;
        }

        if ($borrowing->notification_sent) {
            Log::info('Email notifikasi peminjaman dilewati: notifikasi sudah pernah dikirim.', [
                'borrowing_id' => $borrowing->id,
                'approval_number' => $borrowing->approval_number,
                'mailable' => $mailable::class,
            ]);

            return;
        }

        try {
            Mail::to($email)->send($mailable);
            $borrowing->update(['notification_sent' => true]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim email notifikasi peminjaman ke peminjam.', [
                'borrowing_id' => $borrowing->id,
                'approval_number' => $borrowing->approval_number,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
