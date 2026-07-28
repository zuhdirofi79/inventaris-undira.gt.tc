<?php

namespace App\Mail;

use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BorrowingPendingAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Borrowing $borrowing,
        public User $admin,
    ) {
        $this->borrowing->loadMissing(['inventoryItem', 'items.inventoryItem', 'requestedBy']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pengajuan Peminjaman Inventaris Baru',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.borrowing-pending-admin',
            with: [
                'borrowing' => $this->borrowing,
                'item' => $this->borrowing->inventoryItem,
                'items' => $this->borrowing->items,
                'admin' => $this->admin,
            ],
        );
    }
}
