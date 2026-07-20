<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryConditionMaster extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_conditions';

    protected $fillable = ['code', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(InventoryMasterConfiguration::class, 'inventory_condition_id');
    }
}
