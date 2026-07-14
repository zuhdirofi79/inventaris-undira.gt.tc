<?php

namespace App\Notifications;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OverdueBorrowingNotification extends Notification
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
            'type' => 'overdue_borrowing',
            'title' => 'Peminjaman Terlambat',
            'message' => "Peminjaman {$this->borrowing->borrower_name} terlambat dikembalikan.",
            'borrowing_id' => $this->borrowing->id,
            'inventory_item_id' => $this->borrowing->inventory_item_id,
            'expected_return_date' => $this->borrowing->expected_return_date?->toDateString(),
        ];
    }
}
