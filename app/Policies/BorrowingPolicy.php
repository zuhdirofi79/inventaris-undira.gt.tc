<?php

namespace App\Policies;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Models\Borrowing;
use App\Models\User;

class BorrowingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('borrowing.view');
    }

    public function view(User $user, Borrowing $borrowing): bool
    {
        if (! $user->can('borrowing.view')) {
            return false;
        }

        if ($this->viewsApprovedHistoryOnly($user)) {
            // Own submissions (any status) or Admin-approved history only.
            if ((int) $borrowing->requested_by === (int) $user->id) {
                return true;
            }

            return $this->isApprovedInventoryRecord($borrowing);
        }

        if (! $this->limitsToOwnRecords($user)) {
            return true;
        }

        return (int) $borrowing->requested_by === (int) $user->id;
    }

    /**
     * Viewer melihat dan mengunduh hanya transaksi yang ia ajukan.
     * Staff, admin, dan super-admin tetap mengikuti permission masing-masing.
     */
    public function limitsToOwnRecords(User $user): bool
    {
        return $user->hasRole('viewer')
            && ! $user->hasAnyRole(['super-admin', 'admin', 'staff', 'driver', 'ob']);
    }

    /**
     * Driver / OB may browse all Admin-approved borrowing history (read-only).
     */
    public function viewsApprovedHistoryOnly(User $user): bool
    {
        return $user->hasAnyRole(['driver', 'ob'])
            && ! $user->hasAnyRole(['super-admin', 'admin', 'staff']);
    }

    public function isApprovedInventoryRecord(Borrowing $borrowing): bool
    {
        if (in_array($borrowing->status, [
            BorrowingStatus::Disetujui,
            BorrowingStatus::Dipinjam,
            BorrowingStatus::Dikembalikan,
            BorrowingStatus::Terlambat,
        ], true)) {
            return true;
        }

        return $borrowing->items()
            ->whereIn('status', [
                BorrowingItemStatus::Disetujui,
                BorrowingItemStatus::Dipinjam,
                BorrowingItemStatus::Dikembalikan,
            ])
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('borrowing.create');
    }

    public function update(User $user, Borrowing $borrowing): bool
    {
        return $user->can('borrowing.update');
    }

    public function delete(User $user, Borrowing $borrowing): bool
    {
        return $user->can('borrowing.delete');
    }

    public function approve(User $user, Borrowing $borrowing): bool
    {
        if (! $user->can('borrowing.approve')) {
            return false;
        }

        if ($borrowing->status === BorrowingStatus::MenungguPersetujuan) {
            return true;
        }

        return $borrowing->items()
            ->where('status', BorrowingItemStatus::MenungguPersetujuan)
            ->exists();
    }

    public function reject(User $user, Borrowing $borrowing): bool
    {
        return $this->approve($user, $borrowing);
    }

    /**
     * Physical handover / mark as borrowed after item-level approval.
     */
    public function handover(User $user, Borrowing $borrowing): bool
    {
        if (! $user->can('borrowing.approve')) {
            return false;
        }

        return in_array($borrowing->status, [
            BorrowingStatus::Disetujui,
            BorrowingStatus::Dipinjam,
            BorrowingStatus::Terlambat,
        ], true);
    }
}
