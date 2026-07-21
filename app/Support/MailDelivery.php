<?php

namespace App\Support;

class MailDelivery
{
    /**
     * Whether the app can deliver email without locking users out in local/dev.
     * - log / array: always OK (dev-safe)
     * - smtp: requires host + credentials (or non-loopback host with auth)
     */
    public static function isReady(): bool
    {
        $mailer = (string) config('mail.default', 'log');

        if (in_array($mailer, ['log', 'array'], true)) {
            return true;
        }

        if ($mailer === 'smtp') {
            $host = (string) config('mail.mailers.smtp.host', '');
            $username = config('mail.mailers.smtp.username');
            $password = config('mail.mailers.smtp.password');

            if ($host === '' || in_array($host, ['127.0.0.1', 'localhost'], true)) {
                return filled($username) && filled($password);
            }

            return filled($host);
        }

        return true;
    }

    public static function notReadyMessage(): string
    {
        return 'SMTP belum dikonfigurasi. Silakan reset password melalui database atau aktifkan layanan email.';
    }
}
