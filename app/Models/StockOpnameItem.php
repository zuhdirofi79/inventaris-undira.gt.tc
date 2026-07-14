<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id',
        'inventory_item_id',
        'scanned_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
