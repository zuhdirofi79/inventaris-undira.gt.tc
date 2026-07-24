<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case PendingApproval = 'pending_approval';
    case Revision = 'revision';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu',
            self::InProgress => 'Sedang Dikerjakan',
            self::PendingApproval => 'Menunggu Approval Admin',
            self::Revision => 'Revisi Perbaikan',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Waiting, self::InProgress, self::Revision], true);
    }

    public function isAwaitingApproval(): bool
    {
        return $this === self::PendingApproval;
    }
}
