<?php

namespace App\Support;

use App\Models\Campus;

final class CampusQuery
{
    /**
     * null = semua kampus. 0 = filter yang tidak cocok dengan kampus mana pun.
     * Nilai non-numerik atau array tidak dianggap sebagai objek kampus.
     */
    public static function id(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw) || is_object($raw) || ! is_numeric($raw) || (int) $raw < 1) {
            return 0;
        }

        $id = (int) $raw;

        return Campus::query()->whereKey($id)->exists() ? $id : 0;
    }
}
