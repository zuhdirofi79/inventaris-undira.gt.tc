<?php

namespace App\Mail;

use App\Models\RoomBorrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RoomBorrowingDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RoomBorrowing $borrowing,
    ) {
        $this->borrowing->loadMissing([
            'room.floor.building.campus',
            'room.inventoryItems',
            'approvedBy',
        ]);
    }

    public function envelope(): Envelope
    {
        $number = $this->borrowing->approval_number;
        $verb = $this->borrowing->status?->value === 'ditolak' ? 'Penolakan' : 'Persetujuan';

        return new Envelope(
            subject: $number
                ? "{$verb} Peminjaman Ruangan - {$number}"
                : "{$verb} Peminjaman Ruangan",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.room-borrowing-decision',
        );
    }
}
