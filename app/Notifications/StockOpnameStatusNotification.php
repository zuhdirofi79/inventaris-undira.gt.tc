<?php

namespace App\Notifications;

use App\Models\StockOpname;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StockOpnameStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public StockOpname $stockOpname,
        public string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        [$title, $message] = match ($this->event) {
            'completed' => [
                'Stock Opname Selesai',
                "Stock opname \"{$this->stockOpname->title}\" telah diselesaikan.",
            ],
            default => [
                'Stock Opname Dimulai',
                "Stock opname \"{$this->stockOpname->title}\" sedang berlangsung.",
            ],
        };

        return [
            'type' => 'stock_opname_'.$this->event,
            'title' => $title,
            'message' => $message,
            'stock_opname_id' => $this->stockOpname->id,
            'event' => $this->event,
        ];
    }
}
