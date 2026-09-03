<?php

namespace App\Enums;

enum BorrowingItemStatus: string
{
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dipinjam = 'dipinjam';
    case Dikembalikan = 'dikembalikan';

    public function label(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'Menunggu Persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Dipinjam => 'Dipinjam',
            self::Dikembalikan => 'Dikembalikan',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
            self::Dipinjam => 'primary',
            self::Dikembalikan => 'secondary',
        };
    }

    public function isPendingApproval(): bool
    {
        return $this === self::MenungguPersetujuan;
    }

    public function isApproved(): bool
    {
        return $this === self::Disetujui;
    }

    public function isRejected(): bool
    {
        return $this === self::Ditolak;
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::MenungguPersetujuan,
            self::Disetujui,
            self::Dipinjam,
        ], true);
    }

    public function isBorrowed(): bool
    {
        return $this === self::Dipinjam;
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Ditolak, self::Dikembalikan], true);
    }

    /**
     * Statuses that block a new borrowing for the same inventory item.
     *
     * @return list<self>
     */
    public static function blockingCases(): array
    {
        return [
            self::MenungguPersetujuan,
            self::Disetujui,
            self::Dipinjam,
        ];
    }

    /**
     * @return list<string>
     */
    public static function blockingValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::blockingCases());
    }
}
