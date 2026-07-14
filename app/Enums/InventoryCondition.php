<?php

namespace App\Enums;

enum InventoryCondition: string
{
    case Baru = 'baru';
    case Bagus = 'bagus';
    case Cukup = 'cukup';
    case RusakRingan = 'rusak_ringan';
    case RusakBerat = 'rusak_berat';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Bagus => 'Bagus',
            self::Cukup => 'Cukup',
            self::RusakRingan => 'Rusak Ringan',
            self::RusakBerat => 'Rusak Berat',
        };
    }
}
