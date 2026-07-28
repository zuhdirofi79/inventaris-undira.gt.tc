<?php

namespace App\Services;

use App\Enums\BorrowingStatus;
use App\Enums\InventoryStatus;
use App\Mail\BorrowingApprovedMail;
use App\Mail\BorrowingPendingAdminMail;
use App\Mail\BorrowingRejectedMail;
use App\Models\Borrowing;
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
    ) {}

    /**
     * Create a pending borrowing without changing inventory status.
     */
    public function submit(array $data, User $requester): Borrowing
    {
        $borrowing = DB::transaction(function () use ($data, $requester) {
            return Borrowing::query()->create([
                ...$data,
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
        });

        $borrowing->load(['inventoryItem', 'requestedBy']);

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
            $this->assertInventoryAvailableForApproval($locked);

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

            InventoryItem::query()
                ->whereKey($locked->inventory_item_id)
                ->update(['status' => InventoryStatus::Dipinjam]);

            return $locked->fresh(['inventoryItem.category', 'inventoryItem.subCategory', 'inventoryItem.room', 'approvedBy', 'requestedBy']);
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
                // approval_number stays null on reject — never invent a number for rejected loans
            ]);

            return $locked->fresh(['inventoryItem', 'approvedBy', 'requestedBy']);
        });

        $this->notifications->notifyBorrowingRejected($borrowing);
        $this->sendBorrowerMail($borrowing, new BorrowingRejectedMail($borrowing));
        InventarisCache::flush();

        return $borrowing;
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
        // If somehow already numbered while still pending, keep it — never regenerate.
        if ($borrowing->approval_number && $borrowing->status !== BorrowingStatus::MenungguPersetujuan) {
            throw ValidationException::withMessages([
                'approval_number' => 'Nomor approval tidak boleh diubah.',
            ]);
        }
    }

    protected function assertInventoryAvailableForApproval(Borrowing $borrowing): void
    {
        $item = InventoryItem::query()
            ->whereKey($borrowing->inventory_item_id)
            ->lockForUpdate()
            ->first();

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
                'inventory_item_id' => 'Approval tidak dapat dilakukan karena barang sedang Dipinjam.',
            ]);
        }

        $otherActive = Borrowing::query()
            ->where('inventory_item_id', $borrowing->inventory_item_id)
            ->where('id', '!=', $borrowing->id)
            ->whereIn('status', BorrowingStatus::blockingValues())
            ->where('status', '!=', BorrowingStatus::MenungguPersetujuan->value)
            ->exists();

        if ($otherActive) {
            throw ValidationException::withMessages([
                'inventory_item_id' => 'Approval tidak dapat dilakukan karena barang sedang Dipinjam.',
            ]);
        }
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
