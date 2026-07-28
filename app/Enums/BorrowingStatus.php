<?php

namespace App\Enums;

enum BorrowingStatus: string
{
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dipinjam = 'dipinjam';
    case Dikembalikan = 'dikembalikan';
    case Terlambat = 'terlambat';

    public function label(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'Menunggu Persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Dipinjam => 'Dipinjam',
            self::Dikembalikan => 'Dikembalikan',
            self::Terlambat => 'Terlambat',
        };
    }

    /**
     * Bootstrap badge color class (without bg- prefix).
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::MenungguPersetujuan => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
            self::Dipinjam => 'primary',
            self::Dikembalikan => 'secondary',
            self::Terlambat => 'warning',
        };
    }

    public function isPending(): bool
    {
        return $this === self::MenungguPersetujuan;
    }

    public function isActiveLoan(): bool
    {
        return in_array($this, [self::Disetujui, self::Dipinjam, self::Terlambat], true);
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
            self::Terlambat,
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
