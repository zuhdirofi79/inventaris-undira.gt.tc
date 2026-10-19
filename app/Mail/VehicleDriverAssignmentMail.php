<?php

namespace App\Mail;

use App\Models\VehicleBorrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VehicleDriverAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VehicleBorrowing $borrowing,
    ) {
        $this->borrowing->loadMissing([
            'campus',
            'route',
            'vehicle',
            'driver.user',
            'approvedBy',
        ]);
    }

    public function envelope(): Envelope
    {
        $number = $this->borrowing->approval_number;

        return new Envelope(
            subject: $number
                ? "Penugasan Pengemudi - {$number}"
                : 'Penugasan Pengemudi Peminjaman Kendaraan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.vehicle-driver-assignment',
        );
    }
}
