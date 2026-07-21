<?php

namespace App\Services;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordResetOtpService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function cacheKey(User $user): string
    {
        return 'password_reset_otp:'.$user->id;
    }

    public function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($user), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        Mail::to($user->email)->send(new PasswordResetOtpMail($user, $code));

        return $code;
    }

    public function verify(User $user, string $otp): void
    {
        $payload = Cache::get($this->cacheKey($user));

        if (! is_array($payload) || empty($payload['hash'])) {
            throw ValidationException::withMessages([
                'otp' => 'Kode OTP tidak ditemukan atau sudah kedaluwarsa. Silakan kirim ulang.',
            ]);
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($this->cacheKey($user));
            throw ValidationException::withMessages([
                'otp' => 'Terlalu banyak percobaan. Silakan kirim ulang kode OTP.',
            ]);
        }

        if (! Hash::check($otp, $payload['hash'])) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($this->cacheKey($user), $payload, now()->addMinutes(self::TTL_MINUTES));

            throw ValidationException::withMessages([
                'otp' => 'Kode OTP tidak valid.',
            ]);
        }

        Cache::forget($this->cacheKey($user));
    }
}
