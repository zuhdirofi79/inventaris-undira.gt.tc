<?php

namespace App\Models;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MaintenanceSchedule extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'maintenance_type',
        'interval_value',
        'interval_unit',
        'next_maintenance_date',
        'remind_before_days',
        'responsible_pic',
        'notes',
        'is_active',
        'last_completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_type' => MaintenanceType::class,
            'interval_unit' => MaintenanceIntervalUnit::class,
            'interval_value' => 'integer',
            'next_maintenance_date' => 'date',
            'remind_before_days' => 'integer',
            'is_active' => 'boolean',
            'last_completed_at' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->active()->whereDate('next_maintenance_date', now()->toDateString());
    }

    public function scopeDueThisWeek(Builder $query): Builder
    {
        $start = now()->startOfDay();
        $end = now()->endOfWeek();

        return $query->active()
            ->whereDate('next_maintenance_date', '>=', $start->toDateString())
            ->whereDate('next_maintenance_date', '<=', $end->toDateString());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()->whereDate('next_maintenance_date', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->next_maintenance_date !== null
            && $this->next_maintenance_date->lt(now()->startOfDay());
    }

    public function isDueToday(): bool
    {
        return $this->next_maintenance_date !== null
            && $this->next_maintenance_date->isSameDay(now());
    }

    public function reminderStartsAt(): Carbon
    {
        return $this->next_maintenance_date
            ->copy()
            ->subDays(max(0, (int) $this->remind_before_days))
            ->startOfDay();
    }

    public function isInReminderWindow(): bool
    {
        if (! $this->is_active || ! $this->next_maintenance_date) {
            return false;
        }

        return now()->startOfDay()->gte($this->reminderStartsAt());
    }

    public function intervalLabel(): string
    {
        return $this->interval_value.' '.($this->interval_unit?->label() ?? '');
    }

    public function advanceNextDate(?Carbon $from = null): void
    {
        $base = ($from ?? $this->next_maintenance_date ?? now())->copy()->startOfDay();
        $value = max(1, (int) $this->interval_value);

        $next = match ($this->interval_unit) {
            MaintenanceIntervalUnit::Days => $base->addDays($value),
            MaintenanceIntervalUnit::Weeks => $base->addWeeks($value),
            MaintenanceIntervalUnit::Months => $base->addMonthsNoOverflow($value),
            MaintenanceIntervalUnit::Years => $base->addYearsNoOverflow($value),
            default => $base->addMonthsNoOverflow($value),
        };

        $this->next_maintenance_date = $next;
        $this->last_completed_at = now();
        $this->save();
    }
}
