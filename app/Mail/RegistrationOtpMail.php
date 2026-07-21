<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode Verifikasi Email - Inventaris UNDIRA',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.registration-otp',
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
                'ttlMinutes' => \App\Services\RegistrationOtpService::TTL_MINUTES,
            ],
        );
    }
}
