<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campus extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function stockOpnames(): HasMany
    {
        return $this->hasMany(StockOpname::class);
    }
}
