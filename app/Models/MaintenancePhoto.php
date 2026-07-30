<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MaintenancePhoto extends Model
{
    protected $fillable = [
        'maintenance_id',
        'type',
        'path',
        'caption',
    ];

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function fileExists(): bool
    {
        return filled($this->path) && Storage::disk('public')->exists($this->path);
    }

    /**
     * Public URL for the photo, or null when the file is missing (avoids broken <img>).
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        // Relative /storage/... is robust when APP_URL host differs from the browser host.
        return Storage::disk('public')->url($this->path);
    }
}
