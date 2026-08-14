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
     * Normalized relative path on the public disk (forward slashes, no leading slash / disk prefix).
     */
    public function storagePath(): string
    {
        $path = ltrim(str_replace('\\', '/', (string) $this->path), '/');

        // Normalize accidental prefixes from older saves / manual edits.
        foreach (['storage/app/public/', 'app/public/', 'public/', 'storage/'] as $prefix) {
            if (str_starts_with(strtolower($path), $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        return ltrim($path, '/');
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

        // Prefer an explicit host-relative URL (avoids APP_URL / double-prefix issues).
        $relative = '/storage/'.$path;

        // Still consult the disk for non-local adapters, then normalize to a path.
        $diskUrl = Storage::disk('public')->url($path);
        if (is_string($diskUrl) && $diskUrl !== '') {
            if (str_starts_with($diskUrl, 'http://') || str_starts_with($diskUrl, 'https://')) {
                $parsed = parse_url($diskUrl, PHP_URL_PATH);

                return is_string($parsed) && $parsed !== '' ? $parsed : $relative;
            }

            $normalized = '/'.ltrim(str_replace('\\', '/', $diskUrl), '/');
            // Guard against /storage/storage/...
            $normalized = preg_replace('#^/storage/storage/#', '/storage/', $normalized) ?: $normalized;

            return $normalized;
        }

        return $relative;
    }
}
