<?php

namespace App\Models;

use App\Enums\BorrowingItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorrowingItem extends Model
{
    protected $fillable = [
        'borrowing_id',
        'inventory_item_id',
        'qty',
        'status',
        'borrowed_at',
        'returned_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'status' => BorrowingItemStatus::class,
            'borrowed_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Keputusan approval yang tersimpan, bukan status fisik pinjam/kembali.
     *
     * @return array{0: string, 1: string}
     */
    public function approvalPresentation(): array
    {
        return match ($this->status) {
            BorrowingItemStatus::MenungguPersetujuan => ['Menunggu', 'warning'],
            BorrowingItemStatus::Disetujui,
            BorrowingItemStatus::Dipinjam,
            BorrowingItemStatus::Dikembalikan => ['Disetujui', 'success'],
            BorrowingItemStatus::Ditolak => ['Ditolak', 'danger'],
            default => ['Status Tidak Tersedia', 'secondary'],
        };
    }
}
