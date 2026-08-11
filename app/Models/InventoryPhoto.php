<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InventoryPhoto extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'path',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Browser-accessible URL for the stored file.
     * Always prefer a host-relative path so <img src> works even when
     * APP_URL host differs from the host the user is browsing (localhost vs 127.0.0.1).
     */
    public function getUrlAttribute(): string
    {
        $url = Storage::disk('public')->url(ltrim((string) $this->path, '/'));

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return parse_url($url, PHP_URL_PATH) ?: $url;
        }

        return $url;
    }
}
