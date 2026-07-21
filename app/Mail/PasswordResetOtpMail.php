<?php

namespace App\Mail;

use App\Models\User;
use App\Services\PasswordResetOtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode Reset Password - Inventaris UNDIRA',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.password-reset-otp',
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
                'ttlMinutes' => PasswordResetOtpService::TTL_MINUTES,
            ],
        );
    }
}
