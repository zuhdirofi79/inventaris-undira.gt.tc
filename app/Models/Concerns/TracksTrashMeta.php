<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait TracksTrashMeta
{
    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function restoredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function markTrashedBy(?User $actor): void
    {
        $this->forceFill([
            'deleted_by' => $actor?->id,
            'restored_by' => null,
            'restored_at' => null,
        ])->saveQuietly();
    }

    public function markRestoredBy(?User $actor): void
    {
        $this->forceFill([
            'deleted_by' => null,
            'restored_by' => $actor?->id,
            'restored_at' => now(),
        ])->saveQuietly();
    }
}
