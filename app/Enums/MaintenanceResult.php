<?php

namespace App\Enums;

enum MaintenanceResult: string
{
    case Repaired = 'repaired';
    case CannotRepair = 'cannot_repair';
    case NeedReplacement = 'need_replacement';

    public function label(): string
    {
        return match ($this) {
            self::Repaired => 'Berhasil Diperbaiki',
            self::CannotRepair => 'Tidak Dapat Diperbaiki',
            self::NeedReplacement => 'Perlu Diganti',
        };
    }
}
