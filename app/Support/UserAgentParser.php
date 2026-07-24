<?php

namespace App\Support;

final class UserAgentParser
{
    public static function device(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        if ($ua === '') {
            return 'Unknown';
        }

        return match (true) {
            str_contains($ua, 'ipad') || str_contains($ua, 'tablet') => 'Tablet',
            str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone') => 'Mobile',
            default => 'Desktop',
        };
    }

    public static function browser(?string $userAgent): string
    {
        $ua = (string) $userAgent;
        if ($ua === '') {
            return 'Unknown';
        }

        return match (true) {
            str_contains($ua, 'Edg/') => 'Microsoft Edge',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Edg/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/') => 'Safari',
            str_contains($ua, 'Opera') || str_contains($ua, 'OPR/') => 'Opera',
            default => 'Other',
        };
    }

    public static function summary(?string $userAgent): string
    {
        return self::browser($userAgent).' · '.self::device($userAgent);
    }
}
