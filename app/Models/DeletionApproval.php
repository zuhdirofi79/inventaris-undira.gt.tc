<?php

namespace App\Models;

use App\Enums\DeletionApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletionApproval extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'requested_by',
        'approved_by',
        'reason',
        'status',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeletionApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
