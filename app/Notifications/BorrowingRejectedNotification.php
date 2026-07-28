<?php

namespace App\Notifications;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BorrowingRejectedNotification extends Notification implements ShouldQueue
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
        return [
            'type' => 'borrowing_rejected',
            'title' => 'Peminjaman Ditolak',
            'message' => 'Peminjaman Anda ditolak.',
            'borrowing_id' => $this->borrowing->id,
            'inventory_item_id' => $this->borrowing->inventory_item_id,
            'rejection_reason' => $this->borrowing->rejection_reason,
        ];
    }
}
