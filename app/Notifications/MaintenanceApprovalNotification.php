<?php

namespace App\Notifications;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MaintenanceApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Maintenance $maintenance,
        public string $event,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $item = $this->maintenance->inventoryItem;
        $label = $item?->name ?? 'inventaris';
        $number = $item?->inventory_number;
        $wo = $this->maintenance->maintenance_number;

        [$title, $message, $summary] = match ($this->event) {
            'submitted' => [
                'Approval Maintenance',
                "WO {$wo} untuk {$label}".($number ? " ({$number})" : '').' menunggu approval admin.',
                'Menunggu persetujuan admin',
            ],
            'approved' => [
                'Maintenance Disetujui',
                "WO {$wo} untuk {$label}".($number ? " ({$number})" : '').' telah disetujui. Status inventaris: Aktif.',
                'Disetujui',
            ],
            'rejected' => [
                'Revisi Perbaikan',
                "WO {$wo} dikembalikan ke teknisi".($this->reason ? ': '.$this->reason : '.'),
                $this->reason ? 'Perlu revisi: '.$this->reason : 'Perlu revisi',
            ],
            default => [
                'Maintenance',
                "Update work order {$wo}.",
                'Update work order',
            ],
        };

        return [
            'type' => 'maintenance_approval_'.$this->event,
            'title' => $title,
            'message' => $message,
            'wo_number' => $wo,
            'asset_name' => $label,
            'asset_code' => $number,
            'summary' => $summary,
            'maintenance_id' => $this->maintenance->id,
            'inventory_item_id' => $this->maintenance->inventory_item_id,
            'event' => $this->event,
            'url' => route('maintenances.show', $this->maintenance),
        ];
    }
}
