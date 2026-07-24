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
    case MenungguApproval = 'menunggu_approval';
    case RevisiPerbaikan = 'revisi_perbaikan';
    case TidakDapatDiperbaiki = 'tidak_dapat_diperbaiki';
    case PerluPenggantian = 'perlu_penggantian';
    case Dilelang = 'dilelang';
    case Dimusnahkan = 'dimusnahkan';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::RusakRingan => 'Rusak Ringan',
            self::RusakBerat => 'Rusak Berat',
            self::Hilang => 'Hilang',
            self::Dipinjam => 'Dipinjam',
            self::Maintenance => 'Dalam Perbaikan',
            self::MenungguApproval => 'Menunggu Approval Admin',
            self::RevisiPerbaikan => 'Revisi Perbaikan',
            self::TidakDapatDiperbaiki => 'Tidak Dapat Diperbaiki',
            self::PerluPenggantian => 'Perlu Penggantian',
            self::Dilelang => 'Lelang',
            self::Dimusnahkan => 'Musnah',
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
            self::Maintenance, self::MenungguApproval, self::RevisiPerbaikan => 'maintenance',
            self::RusakRingan, self::RusakBerat, self::TidakDapatDiperbaiki, self::PerluPenggantian => 'damaged',
            self::Hilang, self::Dilelang, self::Dimusnahkan => 'inactive',
        };
    }

    public function isDamagedList(): bool
    {
        return in_array($this, self::damagedCases(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminalCases(), true);
    }

    public function isBorrowable(): bool
    {
        return $this === self::Aktif;
    }

    /**
     * @return list<self>
     */
    public static function damagedCases(): array
    {
        return [
            self::RusakRingan,
            self::RusakBerat,
            self::TidakDapatDiperbaiki,
            self::PerluPenggantian,
        ];
    }

    /**
     * @return list<string>
     */
    public static function damagedValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::damagedCases());
    }

    /**
     * @return list<self>
     */
    public static function terminalCases(): array
    {
        return [
            self::Hilang,
            self::Dilelang,
            self::Dimusnahkan,
        ];
    }

    /**
     * @return list<string>
     */
    public static function terminalValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::terminalCases());
    }
}
