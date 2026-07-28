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
        $this->borrowing->loadMissing('inventoryItem');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Peminjaman Inventaris Disetujui',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.borrowing-approved',
            with: [
                'borrowing' => $this->borrowing,
                'item' => $this->borrowing->inventoryItem,
            ],
        );
    }
}
