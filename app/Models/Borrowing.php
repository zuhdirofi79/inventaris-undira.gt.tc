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
    ];

    protected function casts(): array
    {
        return [
            'borrow_date' => 'date',
            'return_date' => 'date',
            'expected_return_date' => 'date',
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
}
