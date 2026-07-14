<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMutation extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'from_room_id',
        'to_room_id',
        'moved_by',
        'reason',
        'notes',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
