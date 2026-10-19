<?php

namespace App\Mail;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BorrowingCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Borrowing $borrowing,
    ) {
        $this->borrowing->loadMissing([
            'inventoryItem.category',
            'items.inventoryItem.category',
            'cancelledBy',
            'approvedBy',
        ]);
    }

    public function envelope(): Envelope
    {
        $number = $this->borrowing->approval_number;

        return new Envelope(
            subject: $number
                ? "Pembatalan Persetujuan Peminjaman Inventaris - {$number}"
                : 'Pembatalan Persetujuan Peminjaman Inventaris',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.borrowing-cancelled',
            with: [
                'borrowing' => $this->borrowing,
                'items' => $this->borrowing->lineItems(),
            ],
        );
    }
}
