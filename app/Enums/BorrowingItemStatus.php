<?php

namespace App\Enums;

enum BorrowingItemStatus: string
{
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Dipinjam = 'dipinjam';
    case Dikembalikan = 'dikembalikan';

    public function label(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'Menunggu Persetujuan',
            self::Dipinjam => 'Dipinjam',
            self::Dikembalikan => 'Dikembalikan',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'warning',
            self::Dipinjam => 'primary',
            self::Dikembalikan => 'secondary',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::MenungguPersetujuan, self::Dipinjam], true);
    }

    public function isBorrowed(): bool
    {
        return $this === self::Dipinjam;
    }

    /**
     * @return list<self>
     */
    public static function blockingCases(): array
    {
        return [self::MenungguPersetujuan, self::Dipinjam];
    }

    /**
     * @return list<string>
     */
    public static function blockingValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::blockingCases());
    }
}
