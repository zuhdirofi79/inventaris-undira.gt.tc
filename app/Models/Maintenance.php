<?php

namespace App\Models;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceResult;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Maintenance extends Model
{
    protected $fillable = [
        'maintenance_number',
        'inventory_item_id',
        'vendor_id',
        'maintenance_type',
        'priority',
        'technician',
        'service_date',
        'next_schedule',
        'cost',
        'estimated_cost',
        'description',
        'parts_replaced',
        'status',
        'result',
        'notes',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'next_schedule' => 'date',
            'cost' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'status' => MaintenanceStatus::class,
            'maintenance_type' => MaintenanceType::class,
            'priority' => MaintenancePriority::class,
            'result' => MaintenanceResult::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MaintenancePhoto::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(MaintenanceStatusHistory::class)->latest();
    }

    public function photosBefore(): HasMany
    {
        return $this->photos()->where('type', 'before');
    }

    public function photosDuring(): HasMany
    {
        return $this->photos()->where('type', 'during');
    }

    public function photosAfter(): HasMany
    {
        return $this->photos()->where('type', 'after');
    }
}
