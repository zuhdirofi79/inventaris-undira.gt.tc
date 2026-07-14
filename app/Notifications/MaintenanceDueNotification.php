<?php

namespace App\Notifications;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Maintenance $maintenance,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $item = $this->maintenance->inventoryItem;

        return [
            'type' => 'maintenance_due',
            'title' => 'Jadwal Maintenance',
            'message' => "Maintenance {$item?->name} dijadwalkan pada {$this->maintenance->next_schedule?->format('d/m/Y')}.",
            'maintenance_id' => $this->maintenance->id,
            'inventory_item_id' => $this->maintenance->inventory_item_id,
            'next_schedule' => $this->maintenance->next_schedule?->toDateString(),
        ];
    }
}
