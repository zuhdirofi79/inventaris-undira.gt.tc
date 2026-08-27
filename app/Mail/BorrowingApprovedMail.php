<?php

namespace App\Mail;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BorrowingApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Borrowing $borrowing,
    ) {
        $this->borrowing->loadMissing([
            'inventoryItem.category',
            'items.inventoryItem.category',
        ]);
    }

    public function envelope(): Envelope
    {
        $number = $this->borrowing->approval_number;
        $subject = $number
            ? "Pengajuan Peminjaman Inventaris Disetujui - {$number}"
            : 'Pengajuan Peminjaman Inventaris Disetujui';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $items = $this->borrowing->lineItems();

        return new Content(
            markdown: 'emails.borrowing-approved',
            with: [
                'borrowing' => $this->borrowing,
                'item' => $this->borrowing->inventoryItem,
                'items' => $items,
            ],
        );
    }
}
