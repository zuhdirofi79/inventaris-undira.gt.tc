<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DecisionMailer
{
    public function send(?string $email, Mailable $mailable, string $logMessage, int $id): void
    {
        $email = trim((string) $email);
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send($mailable);
        } catch (Throwable $e) {
            Log::warning($logMessage, [
                'id' => $id,
                'exception' => $e::class,
            ]);
        }
    }
}
