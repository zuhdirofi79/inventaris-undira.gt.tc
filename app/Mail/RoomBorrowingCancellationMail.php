<?php

namespace App\Mail;

use App\Models\RoomBorrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RoomBorrowingCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RoomBorrowing $borrowing,
    ) {
        $this->borrowing->loadMissing([
            'room.floor.building.campus',
            'campus',
            'approvedBy',
            'cancelledBy',
        ]);
    }

    public function envelope(): Envelope
    {
        $number = $this->borrowing->approval_number;

        return new Envelope(
            subject: $number
                ? "Pembatalan Persetujuan Peminjaman Ruangan - {$number}"
                : 'Pembatalan Persetujuan Peminjaman Ruangan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.room-borrowing-cancellation',
        );
    }
}
