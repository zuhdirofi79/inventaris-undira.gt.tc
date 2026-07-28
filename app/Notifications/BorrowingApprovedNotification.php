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
        return [
            'type' => 'borrowing_approved',
            'title' => 'Peminjaman Disetujui',
            'message' => 'Peminjaman Anda disetujui.',
            'borrowing_id' => $this->borrowing->id,
            'approval_number' => $this->borrowing->approval_number,
            'inventory_item_id' => $this->borrowing->inventory_item_id,
        ];
    }
}
