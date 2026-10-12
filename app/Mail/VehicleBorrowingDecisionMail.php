<?php

namespace App\Mail;

use App\Models\VehicleBorrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VehicleBorrowingDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VehicleBorrowing $borrowing,
    ) {
        $this->borrowing->loadMissing(['campus', 'route', 'vehicle', 'driver', 'approvedBy']);
    }

    public function envelope(): Envelope
    {
        $number = $this->borrowing->approval_number;
        $verb = $this->borrowing->status?->value === 'ditolak' ? 'Penolakan' : 'Persetujuan';

        return new Envelope(
            subject: $number
                ? "{$verb} Peminjaman Kendaraan - {$number}"
                : "{$verb} Peminjaman Kendaraan",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.vehicle-borrowing-decision',
        );
    }
}
