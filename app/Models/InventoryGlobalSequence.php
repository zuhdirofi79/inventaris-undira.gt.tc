<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryGlobalSequence extends Model
{
    protected $fillable = [
        'last_value',
    ];

    protected function casts(): array
    {
        return [
            'last_value' => 'integer',
        ];
    }
}
