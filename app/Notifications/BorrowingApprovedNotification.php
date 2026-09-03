<?php

namespace App\Notifications;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BorrowingApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Borrowing $borrowing,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->borrowing->loadMissing('items');
        $counts = $this->borrowing->itemDecisionCounts();
        $isPartial = $counts['approved'] > 0 && $counts['rejected'] > 0;

        $message = $isPartial
            ? "{$counts['approved']} inventaris disetujui dan {$counts['rejected']} inventaris ditolak."
            : 'Peminjaman Anda telah disetujui.';

        return [
            'type' => 'borrowing_approved',
            'title' => $isPartial ? 'Peminjaman Disetujui Sebagian' : 'Peminjaman Disetujui',
            'message' => $message,
            'borrowing_id' => $this->borrowing->id,
            'approval_number' => $this->borrowing->approval_number,
            'inventory_item_id' => $this->borrowing->inventory_item_id,
            'approved_count' => $counts['approved'],
            'rejected_count' => $counts['rejected'],
        ];
    }
}
