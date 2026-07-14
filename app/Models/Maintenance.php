<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'vendor_id',
        'service_date',
        'next_schedule',
        'cost',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'next_schedule' => 'date',
            'cost' => 'decimal:2',
            'status' => MaintenanceStatus::class,
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
