<?php

namespace App\Enums;

enum MaintenanceType: string
{
    case Repair = 'repair';
    case Inspection = 'inspection';
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Cleaning = 'cleaning';
    case Calibration = 'calibration';
    case Upgrade = 'upgrade';
    case Replacement = 'replacement';

    public function label(): string
    {
        return match ($this) {
            self::Repair => 'Perbaikan',
            self::Inspection => 'Inspeksi',
            self::Preventive => 'Preventif',
            self::Corrective => 'Korektif',
            self::Cleaning => 'Pembersihan',
            self::Calibration => 'Kalibrasi',
            self::Upgrade => 'Upgrade',
            self::Replacement => 'Penggantian',
        };
    }
}
