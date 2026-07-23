<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case Aktif = 'aktif';
    case RusakRingan = 'rusak_ringan';
    case RusakBerat = 'rusak_berat';
    case Hilang = 'hilang';
    case Dipinjam = 'dipinjam';
    case Maintenance = 'maintenance';
    case TidakDapatDiperbaiki = 'tidak_dapat_diperbaiki';
    case PerluPenggantian = 'perlu_penggantian';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::RusakRingan => 'Rusak Ringan',
            self::RusakBerat => 'Rusak Berat',
            self::Hilang => 'Hilang',
            self::Dipinjam => 'Dipinjam',
            self::Maintenance => 'Dalam Pemeliharaan',
            self::TidakDapatDiperbaiki => 'Tidak Dapat Diperbaiki',
            self::PerluPenggantian => 'Perlu Penggantian',
        };
    }

    /**
     * Semantic tone for pastel badge styling (not raw DB value).
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::Aktif => 'active',
            self::Dipinjam => 'borrowed',
            self::Maintenance => 'maintenance',
            self::RusakRingan, self::RusakBerat, self::TidakDapatDiperbaiki, self::PerluPenggantian => 'damaged',
            self::Hilang => 'inactive',
        };
    }

    public function isDamagedList(): bool
    {
        return in_array($this, [
            self::RusakRingan,
            self::RusakBerat,
            self::TidakDapatDiperbaiki,
            self::PerluPenggantian,
        ], true);
    }

    public function isBorrowable(): bool
    {
        return $this === self::Aktif;
    }
}
