<?php

namespace App\Mail;

use App\Models\VehicleBorrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VehicleBorrowingCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VehicleBorrowing $borrowing,
    ) {
        $this->borrowing->loadMissing([
            'campus',
            'route',
            'approvedBy',
            'cancelledBy',
        ]);
    }

    public function envelope(): Envelope
    {
        $number = $this->borrowing->approval_number;

        return new Envelope(
            subject: $number
                ? "Pembatalan Persetujuan Peminjaman Kendaraan - {$number}"
                : 'Pembatalan Persetujuan Peminjaman Kendaraan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.vehicle-borrowing-cancellation',
        );
    }
}
