<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IndonesianPhoneNumber implements ValidationRule
{
    /**
     * 08, 62, or +62, then at least four digits. Spaces and hyphens may separate digits.
     */
    public const PATTERN = '/^(?:\+62|62|08)(?:[\s\-]*\d){4,}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = trim((string) $value);

        if (! preg_match(self::PATTERN, $phone)) {
            $fail('Nomor Telepon / No. HP tidak valid. Awali dengan 08, 62, atau +62, lalu isi minimal 4 digit. Spasi dan tanda hubung diperbolehkan.');
        }
    }
}
