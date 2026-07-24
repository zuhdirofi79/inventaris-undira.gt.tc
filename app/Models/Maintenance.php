<?php

namespace App\Models;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceResult;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Maintenance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'maintenance_number',
        'inventory_item_id',
        'vendor_id',
        'maintenance_type',
        'priority',
        'technician',
        'technician_user_id',
        'service_date',
        'next_schedule',
        'estimated_completion',
        'cost',
        'estimated_cost',
        'description',
        'damage_type',
        'analysis',
        'work_performed',
        'parts_replaced',
        'status',
        'result',
        'notes',
        'started_at',
        'completed_at',
        'submitted_for_approval_at',
        'approved_by',
        'approved_at',
        'approval_note',
        'rejection_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'next_schedule' => 'date',
            'estimated_completion' => 'date',
            'cost' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'status' => MaintenanceStatus::class,
            'maintenance_type' => MaintenanceType::class,
            'priority' => MaintenancePriority::class,
            'result' => MaintenanceResult::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'submitted_for_approval_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function technicianUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    public function durationHours(): ?float
    {
        if (! $this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? $this->approved_at ?? now();

        return round($this->started_at->diffInMinutes($end) / 60, 2);
    }
}
