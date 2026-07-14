<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WarrantyExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        public InventoryItem $item,
        public int $daysRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'warranty_expiring',
            'title' => 'Garansi Akan Berakhir',
            'message' => "Garansi {$this->item->name} ({$this->item->inventory_number}) berakhir dalam {$this->daysRemaining} hari.",
            'inventory_item_id' => $this->item->id,
            'inventory_number' => $this->item->inventory_number,
            'warranty_expiry' => $this->item->warranty_expiry?->toDateString(),
        ];
    }
}
