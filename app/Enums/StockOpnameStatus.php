<?php

namespace App\Enums;

enum StockOpnameStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InProgress => 'Sedang Berlangsung',
            self::Completed => 'Selesai',
        };
    }
}
