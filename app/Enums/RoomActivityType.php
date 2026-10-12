<?php

namespace App\Enums;

enum RoomActivityType: string
{
    case Rapat = 'rapat';
    case Akademik = 'akademik';
    case Seminar = 'seminar';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Rapat => 'Rapat',
            self::Akademik => 'Kegiatan Akademik',
            self::Seminar => 'Seminar',
            self::Lainnya => 'Kegiatan Lainnya',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
