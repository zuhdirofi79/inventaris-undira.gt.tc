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
    ) {}

    /**
     * Create a pending borrowing (header + items) without changing inventory status.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data, User $requester): Borrowing
    {
        $inventoryIds = $this->itemResolver->normalizeRequestItems($data);
        $header = collect($data)->except([
            'inventory_item_id',
            'inventory_item_ids',
            'items',
            'status',
        ])->all();

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
            ]);

            foreach ($this->itemResolver->buildItemRows($inventoryIds) as $row) {
                $borrowing->items()->create($row);
            }

            return $borrowing;
        });

        $borrowing->load(['items.inventoryItem', 'inventoryItem', 'requestedBy']);

        $this->notifications->notifyBorrowingCreated($borrowing);
        $this->notifyAdminsByEmail($borrowing);
        InventarisCache::flush();

        return $borrowing;
    }

    public function approve(Borrowing $borrowing, User $approver, ?string $notes = null): Borrowing
    {
        $this->assertPending($borrowing);
        $this->assertNotSelfApproval($borrowing, $approver);

        $borrowing = DB::transaction(function () use ($borrowing, $approver, $notes) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);
            $this->assertApprovalNumberImmutable($locked);
            $this->ensureItems($locked);
            $this->assertItemsAvailableForApproval($locked);

            $approvalNumber = $locked->approval_number ?: $this->approvalNumbers->generate();

            $locked->update([
                'status' => BorrowingStatus::Disetujui,
                'approval_number' => $approvalNumber,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
                'approval_notes' => $notes,
            ]);

            $itemIds = $this->inventoryIdsFor($locked);

            BorrowingItem::query()
                ->where('borrowing_id', $locked->id)
                ->where('status', BorrowingItemStatus::MenungguPersetujuan)
                ->update(['status' => BorrowingItemStatus::Dipinjam]);

            if ($itemIds !== []) {
                InventoryItem::query()
                    ->whereIn('id', $itemIds)
                    ->update(['status' => InventoryStatus::Dipinjam]);
            }

            return $locked->fresh([
                'items.inventoryItem.category',
                'items.inventoryItem.subCategory',
                'items.inventoryItem.room',
                'inventoryItem.category',
                'inventoryItem.subCategory',
                'inventoryItem.room',
                'approvedBy',
                'requestedBy',
            ]);
        });

        $this->notifications->notifyBorrowingApproved($borrowing);
        $this->sendBorrowerMail($borrowing, new BorrowingApprovedMail($borrowing));
        InventarisCache::flush();

        return $borrowing;
    }

    public function reject(Borrowing $borrowing, User $approver, string $reason): Borrowing
    {
        $this->assertPending($borrowing);
        $this->assertNotSelfApproval($borrowing, $approver);

        $borrowing = DB::transaction(function () use ($borrowing, $approver, $reason) {
            $locked = Borrowing::query()->whereKey($borrowing->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            $locked->update([
                'status' => BorrowingStatus::Ditolak,
                'approved_by' => $approver->id,
                'approved_at' => null,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'approval_notes' => null,
            ]);

            // Items stay menunggu / unused — inventory never flipped on submit.
            return $locked->fresh([
                'items.inventoryItem',
                'inventoryItem',
                'approvedBy',
                'requestedBy',
            ]);
        });

        $this->notifications->notifyBorrowingRejected($borrowing);
        $this->sendBorrowerMail($borrowing, new BorrowingRejectedMail($borrowing));
        InventarisCache::flush();

        return $borrowing;
    }

    /**
     * Return selected line items (or all open lines). Updates header when all returned.
     *
     * @param  list<int>|null  $borrowingItemIds  null = return all open items
     */
    public function returnItems(Borrowing $borrowing, ?array $borrowingItemIds = null): Borrowing
    {
        return DB::transaction(function () use ($borrowing, $borrowingItemIds) {
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

            $now = now();
            foreach ($lines as $line) {
                $line->update([
                    'status' => BorrowingItemStatus::Dikembalikan,
                    'returned_at' => $now,
                ]);
            }

            InventoryItem::query()
                ->whereIn('id', $lines->pluck('inventory_item_id')->all())
                ->update(['status' => InventoryStatus::Aktif]);

            $locked->load('items');

            if ($locked->allItemsReturned()) {
                $locked->update([
                    'status' => BorrowingStatus::Dikembalikan,
                    'return_date' => $locked->return_date ?? $now->toDateString(),
                ]);
            }

            InventarisCache::flush();

            return $locked->fresh([
                'items.inventoryItem.category',
                'items.inventoryItem.subCategory',
                'items.inventoryItem.room.floor.building.campus',
                'inventoryItem',
                'approvedBy',
                'requestedBy',
            ]);
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

        $ids = $this->inventoryIdsFor($borrowing);
        if ($ids === []) {
            return;
        }

        InventoryItem::query()
            ->whereIn('id', $ids)
            ->where('status', InventoryStatus::Dipinjam)
            ->update(['status' => InventoryStatus::Aktif]);
    }

    protected function assertPending(Borrowing $borrowing): void
    {
        if ($borrowing->status !== BorrowingStatus::MenungguPersetujuan) {
            throw ValidationException::withMessages([
                'status' => 'Peminjaman ini sudah diproses dan tidak dapat diapprove/reject ulang.',
            ]);
        }
    }

    protected function assertNotSelfApproval(Borrowing $borrowing, User $approver): void
    {
        if ($borrowing->requested_by !== null && (int) $borrowing->requested_by === (int) $approver->id) {
            throw ValidationException::withMessages([
                'approved_by' => 'Anda tidak dapat menyetujui atau menolak pengajuan yang Anda buat sendiri.',
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

    protected function assertItemsAvailableForApproval(Borrowing $borrowing): void
    {
        $ids = $this->inventoryIdsFor($borrowing);

        if ($ids === []) {
            throw ValidationException::withMessages([
                'items' => 'Peminjaman tidak memiliki daftar barang.',
            ]);
        }

        $items = InventoryItem::query()
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
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
                    'inventory_item_id' => "Approval tidak dapat dilakukan karena {$item->inventory_number} sedang Dipinjam.",
                ]);
            }

            if (Borrowing::inventoryIsBlocked((int) $id, (int) $borrowing->id)) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => "Approval tidak dapat dilakukan karena {$item->inventory_number} sedang Dipinjam.",
                ]);
            }
        }
    }

    /**
     * @return list<int>
     */
    protected function inventoryIdsFor(Borrowing $borrowing): array
    {
        $ids = BorrowingItem::query()
            ->where('borrowing_id', $borrowing->id)
            ->pluck('inventory_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids !== []) {
            return $ids;
        }

        return $borrowing->inventory_item_id ? [(int) $borrowing->inventory_item_id] : [];
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
            BorrowingStatus::Disetujui,
            BorrowingStatus::Dipinjam,
            BorrowingStatus::Terlambat => BorrowingItemStatus::Dipinjam,
            default => BorrowingItemStatus::MenungguPersetujuan,
        };

        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $borrowing->inventory_item_id,
            'qty' => 1,
            'status' => $status,
            'returned_at' => $status === BorrowingItemStatus::Dikembalikan ? now() : null,
        ]);
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
            return;
        }

        try {
            Mail::to($email)->send($mailable);
            $borrowing->update(['notification_sent' => true]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim email notifikasi peminjaman ke peminjam.', [
                'borrowing_id' => $borrowing->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
