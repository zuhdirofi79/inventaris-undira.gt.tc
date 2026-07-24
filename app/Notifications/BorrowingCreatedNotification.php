<?php

namespace App\Notifications;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BorrowingCreatedNotification extends Notification implements ShouldQueue
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
        $item = $this->borrowing->inventoryItem;

        return [
            'type' => 'borrowing_created',
            'title' => 'Barang Dipinjam',
            'message' => "{$this->borrowing->borrower_name} meminjam {$item?->name} ({$item?->inventory_number}).",
            'borrowing_id' => $this->borrowing->id,
            'inventory_item_id' => $this->borrowing->inventory_item_id,
            'expected_return_date' => $this->borrowing->expected_return_date?->toDateString(),
        ];
    }
}
