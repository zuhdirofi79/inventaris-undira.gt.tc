<?php

namespace App\Models;

use App\Models\Concerns\TracksTrashMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryMasterConfiguration extends Model
{
    use SoftDeletes;
    use TracksTrashMeta;

    protected $fillable = [
        'campus_id',
        'building_id',
        'floor_id',
        'org_unit_id',
        'room_function_id',
        'room_id',
        'procurement_type_id',
        'purchase_year_id',
        'category_id',
        'sub_category_id',
        'inventory_condition_id',
        'generated_location_code',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function roomFunction(): BelongsTo
    {
        return $this->belongsTo(RoomFunction::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function procurementType(): BelongsTo
    {
        return $this->belongsTo(ProcurementType::class);
    }

    public function purchaseYear(): BelongsTo
    {
        return $this->belongsTo(PurchaseYear::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function inventoryCondition(): BelongsTo
    {
        return $this->belongsTo(InventoryConditionMaster::class, 'inventory_condition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
