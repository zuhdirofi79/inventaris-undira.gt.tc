<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'floor_id',
        'code',
        'name',
        'room_type',
        'capacity',
        'is_active',
    ];

    protected $appends = [
        'full_location',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function getFullLocationAttribute(): string
    {
        $floor = $this->relationLoaded('floor') ? $this->floor : $this->floor()->with('building.campus')->first();
        $building = $floor?->building;
        $campus = $building?->campus;

        return collect([
            $campus?->name,
            $building?->name,
            $floor?->name,
            $this->name,
        ])->filter()->implode(' / ');
    }
}
