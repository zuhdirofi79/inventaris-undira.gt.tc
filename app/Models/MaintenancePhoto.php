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

    /**
     * Normalized relative path on the public disk (forward slashes, no leading slash).
     */
    public function storagePath(): string
    {
        return ltrim(str_replace('\\', '/', (string) $this->path), '/');
    }

    public function fileExists(): bool
    {
        $path = $this->storagePath();

        return $path !== '' && Storage::disk('public')->exists($path);
    }

    /**
     * Browser-accessible URL for the stored file.
     * Host-relative (/storage/...) so <img src> works when APP_URL host
     * differs from the host the user is browsing (same pattern as InventoryPhoto).
     */
    public function getUrlAttribute(): ?string
    {
        $path = $this->storagePath();
        if ($path === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return parse_url($url, PHP_URL_PATH) ?: $url;
        }

        return $url;
    }
}
