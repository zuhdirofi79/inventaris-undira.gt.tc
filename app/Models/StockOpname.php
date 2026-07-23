<?php

namespace App\Models;

use App\Enums\StockOpnameStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    protected $fillable = [
        'title',
        'campus_id',
        'started_at',
        'completed_at',
        'status',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => StockOpnameStatus::class,
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function canStart(): bool
    {
        return $this->status === StockOpnameStatus::Draft;
    }

    public function canScan(): bool
    {
        return $this->status === StockOpnameStatus::InProgress;
    }

    public function canComplete(): bool
    {
        return $this->status === StockOpnameStatus::InProgress;
    }

    public function canDelete(): bool
    {
        return in_array($this->status, [
            StockOpnameStatus::Draft,
            StockOpnameStatus::InProgress,
        ], true);
    }
}
