<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySequence extends Model
{
    protected $fillable = [
        'campus_id',
        'classifier_type',
        'classifier_id',
        'last_value',
    ];

    protected function casts(): array
    {
        return [
            'last_value' => 'integer',
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }
}
