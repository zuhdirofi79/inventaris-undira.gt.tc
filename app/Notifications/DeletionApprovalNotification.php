<?php

namespace App\Notifications;

use App\Models\DeletionApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeletionApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public DeletionApproval $approval,
        public string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $item = $this->approval->inventoryItem;
        $label = $item?->name ?? 'inventaris';
        $number = $item?->inventory_number;

        [$title, $message] = match ($this->event) {
            'approved' => [
                'Penghapusan Disetujui',
                "Permintaan hapus {$label}".($number ? " ({$number})" : '').' telah disetujui.',
            ],
            'rejected' => [
                'Penghapusan Ditolak',
                "Permintaan hapus {$label}".($number ? " ({$number})" : '').' ditolak.',
            ],
            default => [
                'Persetujuan Penghapusan',
                "Ada permintaan hapus untuk {$label}".($number ? " ({$number})" : '').'.',
            ],
        };

        return [
            'type' => 'deletion_approval_'.$this->event,
            'title' => $title,
            'message' => $message,
            'deletion_approval_id' => $this->approval->id,
            'inventory_item_id' => $this->approval->inventory_item_id,
            'event' => $this->event,
        ];
    }
}
