<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts common Google Maps share/short links over HTTP(S) only.
 * Does not fetch or follow redirects.
 */
class GoogleMapsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('Link Google Maps Tujuan tidak valid.');

            return;
        }

        $url = trim($value);
        if ($url === '') {
            return;
        }

        if (strlen($url) > 2048) {
            $fail('Link Google Maps Tujuan maksimal 2048 karakter.');

            return;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            $fail('Link Google Maps Tujuan harus berupa URL yang valid.');

            return;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('Link Google Maps Tujuan harus menggunakan http atau https.');

            return;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || ! $this->isAllowedHost($host)) {
            $fail('Link Google Maps Tujuan harus berasal dari domain Google Maps yang didukung.');

            return;
        }

        $path = (string) ($parts['path'] ?? '');
        if ($this->requiresMapsPath($host) && ! $this->hasMapsPath($path)) {
            $fail('Link Google Maps Tujuan tidak dikenali. Gunakan tautan dari Google Maps.');
        }
    }

    private function isAllowedHost(string $host): bool
    {
        $allowed = [
            'maps.google.com',
            'www.maps.google.com',
            'maps.app.goo.gl',
            'goo.gl',
        ];

        if (in_array($host, $allowed, true)) {
            return true;
        }

        // google.com / www.google.com / google.co.id / www.google.co.id / ...
        return (bool) preg_match('/^(?:www\.)?google\.[a-z.]{2,}$/', $host);
    }

    private function requiresMapsPath(string $host): bool
    {
        return (bool) preg_match('/^(?:www\.)?google\./', $host)
            || $host === 'goo.gl';
    }

    private function hasMapsPath(string $path): bool
    {
        $normalized = '/'.ltrim(strtolower($path), '/');

        return str_starts_with($normalized, '/maps')
            || str_contains($normalized, '/maps/');
    }
}
