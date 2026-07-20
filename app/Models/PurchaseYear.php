<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseYear extends Model
{
    use SoftDeletes;

    protected $fillable = ['year', 'is_active'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(InventoryMasterConfiguration::class);
    }
}
