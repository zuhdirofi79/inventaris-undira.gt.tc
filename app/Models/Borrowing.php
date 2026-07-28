<?php

namespace App\Models;

use App\Enums\BorrowingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Borrowing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'inventory_item_id',
        'borrower_name',
        'borrower_email',
        'borrower_phone',
        'borrow_date',
        'return_date',
        'expected_return_date',
        'status',
        'notes',
        'approved_by',
        'requested_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'approval_notes',
        'approval_number',
        'notification_sent',
    ];

    protected function casts(): array
    {
        return [
            'borrow_date' => 'date',
            'return_date' => 'date',
            'expected_return_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'notification_sent' => 'boolean',
            'status' => BorrowingStatus::class,
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvalStatusLabel(): string
    {
        if ($this->status === BorrowingStatus::MenungguPersetujuan) {
            return 'Menunggu Persetujuan';
        }

        if ($this->status === BorrowingStatus::Ditolak || $this->rejected_at) {
            return 'Ditolak';
        }

        if ($this->approved_at || in_array($this->status, [
            BorrowingStatus::Disetujui,
            BorrowingStatus::Dipinjam,
            BorrowingStatus::Terlambat,
            BorrowingStatus::Dikembalikan,
        ], true)) {
            return 'Disetujui';
        }

        return $this->status?->label() ?? '-';
    }
}
