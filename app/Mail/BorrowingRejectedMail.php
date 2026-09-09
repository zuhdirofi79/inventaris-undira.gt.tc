<?php

namespace App\Mail;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BorrowingRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Borrowing $borrowing,
    ) {
        $this->borrowing->loadMissing([
            'inventoryItem.category',
            'inventoryItem.room.floor.building.campus',
            'items.inventoryItem.category',
            'items.inventoryItem.room.floor.building.campus',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengajuan Peminjaman Inventaris Tidak Disetujui',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.borrowing-rejected',
            with: [
                'borrowing' => $this->borrowing,
                'item' => $this->borrowing->inventoryItem,
                'items' => $this->borrowing->lineItems(),
            ],
        );
    }
}
