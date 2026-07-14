<?php

namespace App\Enums;

enum BorrowingStatus: string
{
    case Dipinjam = 'dipinjam';
    case Dikembalikan = 'dikembalikan';
    case Terlambat = 'terlambat';

    public function label(): string
    {
        return match ($this) {
            self::Dipinjam => 'Dipinjam',
            self::Dikembalikan => 'Dikembalikan',
            self::Terlambat => 'Terlambat',
        };
    }
}
