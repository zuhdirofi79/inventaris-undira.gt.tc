<?php

namespace App\Enums;

enum MaintenanceIntervalUnit: string
{
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case Years = 'years';

    public function label(): string
    {
        return match ($this) {
            self::Days => 'Hari',
            self::Weeks => 'Minggu',
            self::Months => 'Bulan',
            self::Years => 'Tahun',
        };
    }
}
