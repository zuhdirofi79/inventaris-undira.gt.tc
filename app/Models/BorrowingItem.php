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
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'status' => BorrowingItemStatus::class,
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
}
