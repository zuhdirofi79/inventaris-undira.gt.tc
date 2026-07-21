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

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::RusakRingan => 'Rusak Ringan',
            self::RusakBerat => 'Rusak Berat',
            self::Hilang => 'Hilang',
            self::Dipinjam => 'Dipinjam',
            self::Maintenance => 'Maintenance',
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
            self::RusakRingan, self::RusakBerat => 'damaged',
            self::Hilang => 'inactive',
        };
    }
}
