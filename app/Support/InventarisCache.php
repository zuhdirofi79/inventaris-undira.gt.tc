<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Shared cache bust token for dashboard stats, location trees, and related aggregates.
 */
final class InventarisCache
{
    public const BUST_KEY = 'inventaris.data.bust';

    public const TTL = 60;

    public static function bust(): int
    {
        return (int) Cache::get(self::BUST_KEY, 0);
    }

    public static function flush(): void
    {
        Cache::increment(self::BUST_KEY);
    }

    public static function key(string $suffix): string
    {
        return 'inventaris.'.self::bust().'.'.$suffix;
    }
}
