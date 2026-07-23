<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InventoryAttachment extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'type',
        'path',
        'original_name',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'invoice' => 'Invoice',
            'document' => 'Dokumen',
            default => (string) $this->type,
        };
    }
}
