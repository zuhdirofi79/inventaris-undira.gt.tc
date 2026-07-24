<?php

namespace App\Models;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Concerns\TracksTrashMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class InventoryItem extends Model
{
    use SoftDeletes;
    use TracksTrashMeta;

    protected $fillable = [
        'inventory_number',
        'barcode',
        'qr_code_path',
        'name',
        'category_id',
        'sub_category_id',
        'brand',
        'type',
        'model',
        'serial_number',
        'specification',
        'purchase_year',
        'purchase_date',
        'funding_source',
        'procurement_type_id',
        'price',
        'depreciation_value',
        'condition',
        'status',
        'room_id',
        'org_unit_id',
        'responsible_person',
        'pic_id',
        'supplier_id',
        'vendor_id',
        'warranty_info',
        'warranty_expiry',
        'notes',
        'quantity',
        'unit',
        'group_key',
        'unit_sequence',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'full_location',
        'qr_url',
    ];

    protected function casts(): array
    {
        return [
            'purchase_year' => 'integer',
            'purchase_date' => 'date',
            'price' => 'decimal:2',
            'depreciation_value' => 'decimal:2',
            'condition' => InventoryCondition::class,
            'status' => InventoryStatus::class,
            'warranty_expiry' => 'date',
            'quantity' => 'integer',
            'unit_sequence' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function procurementType(): BelongsTo
    {
        return $this->belongsTo(ProcurementType::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Display name for PIC: prefer linked user, fallback to legacy free-text.
     */
    public function picDisplayName(): ?string
    {
        $name = $this->pic?->name ?? $this->responsible_person;

        return filled($name) ? (string) $name : null;
    }

    public function locationDisplayName(): string
    {
        $room = $this->room;
        if (! $room) {
            return '—';
        }

        $floor = $room->floor;
        $building = $floor?->building;
        $campus = $building?->campus;

        $parts = array_filter([
            $campus?->name,
            $building?->name,
            $floor?->name,
            trim(($room->code ? $room->code.' ' : '').$room->name),
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InventoryPhoto::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InventoryAttachment::class);
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(InventoryMutation::class);
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function deletionApprovals(): HasMany
    {
        return $this->hasMany(DeletionApproval::class);
    }

    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function getFullLocationAttribute(): ?string
    {
        if (! $this->room_id) {
            return null;
        }

        $room = $this->relationLoaded('room') ? $this->room : $this->room()->with('floor.building.campus')->first();

        return $room?->full_location;
    }

    public function getQrUrlAttribute(): ?string
    {
        if (! $this->id || (blank($this->qr_code_path) && blank($this->inventory_number))) {
            return null;
        }

        // Relative route — host-independent (fixes APP_URL vs 127.0.0.1 mismatch).
        return route('inventory.qr', $this, false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', InventoryStatus::Aktif);
    }

    public function scopeStatus(Builder $query, InventoryStatus|string $status): Builder
    {
        $value = $status instanceof InventoryStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    public function scopeCondition(Builder $query, InventoryCondition|string $condition): Builder
    {
        $value = $condition instanceof InventoryCondition ? $condition->value : $condition;

        return $query->where('condition', $value);
    }

    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeInSubCategory(Builder $query, int $subCategoryId): Builder
    {
        return $query->where('sub_category_id', $subCategoryId);
    }

    public function scopeInRoom(Builder $query, int $roomId): Builder
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeForCampus(Builder $query, int $campusId): Builder
    {
        return $query->whereIn('room_id', function ($q) use ($campusId) {
            $q->select('rooms.id')
                ->from('rooms')
                ->join('floors', 'floors.id', '=', 'rooms.floor_id')
                ->join('buildings', 'buildings.id', '=', 'floors.building_id')
                ->where('buildings.campus_id', $campusId)
                ->whereNull('rooms.deleted_at')
                ->whereNull('floors.deleted_at')
                ->whereNull('buildings.deleted_at');
        });
    }

    public function scopeForBuilding(Builder $query, int $buildingId): Builder
    {
        return $query->whereIn('room_id', function ($q) use ($buildingId) {
            $q->select('rooms.id')
                ->from('rooms')
                ->join('floors', 'floors.id', '=', 'rooms.floor_id')
                ->where('floors.building_id', $buildingId)
                ->whereNull('rooms.deleted_at')
                ->whereNull('floors.deleted_at');
        });
    }

    public function scopeForFloor(Builder $query, int $floorId): Builder
    {
        return $query->whereIn('room_id', function ($q) use ($floorId) {
            $q->select('id')
                ->from('rooms')
                ->where('floor_id', $floorId)
                ->whereNull('deleted_at');
        });
    }

    public function scopeBySupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeByGroupKey(Builder $query, string $groupKey): Builder
    {
        return $query->where('group_key', $groupKey);
    }

    public function scopePurchaseYear(Builder $query, int $year): Builder
    {
        return $query->where('purchase_year', $year);
    }

    public function scopeWarrantyExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('warranty_expiry')
            ->whereBetween('warranty_expiry', [now(), now()->addDays($days)]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('inventory_number', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")
                ->orWhere('responsible_person', 'like', "%{$term}%")
                ->orWhereHas('pic', fn (Builder $picQuery) => $picQuery->where('name', 'like', "%{$term}%"))
                ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$term}%"))
                ->orWhereHas('room', fn (Builder $roomQuery) => $roomQuery
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%"));
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $q, $search) => $q->search($search))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->status($status))
            ->when($filters['condition'] ?? null, fn (Builder $q, $condition) => $q->condition($condition))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->inCategory((int) $id))
            ->when($filters['sub_category_id'] ?? null, fn (Builder $q, $id) => $q->inSubCategory((int) $id))
            ->when($filters['org_unit_id'] ?? null, fn (Builder $q, $id) => $q->where('org_unit_id', (int) $id))
            ->when($filters['pic_id'] ?? null, fn (Builder $q, $id) => $q->where('pic_id', (int) $id))
            ->when($filters['room_id'] ?? null, fn (Builder $q, $id) => $q->inRoom((int) $id))
            ->when($filters['campus_id'] ?? null, fn (Builder $q, $id) => $q->forCampus((int) $id))
            ->when($filters['building_id'] ?? null, fn (Builder $q, $id) => $q->forBuilding((int) $id))
            ->when($filters['floor_id'] ?? null, fn (Builder $q, $id) => $q->forFloor((int) $id))
            ->when($filters['supplier_id'] ?? null, fn (Builder $q, $id) => $q->bySupplier((int) $id))
            ->when($filters['vendor_id'] ?? null, fn (Builder $q, $id) => $q->byVendor((int) $id))
            ->when($filters['procurement_type_id'] ?? null, fn (Builder $q, $id) => $q->where('procurement_type_id', (int) $id))
            ->when($filters['inventory_number'] ?? null, fn (Builder $q, $v) => $q->where('inventory_number', 'like', '%'.$v.'%'))
            ->when($filters['name'] ?? null, fn (Builder $q, $v) => $q->where('name', 'like', '%'.$v.'%'))
            ->when($filters['serial_number'] ?? null, fn (Builder $q, $v) => $q->where('serial_number', 'like', '%'.$v.'%'))
            ->when($filters['purchase_year'] ?? null, fn (Builder $q, $year) => $q->purchaseYear((int) $year))
            ->when($filters['group_key'] ?? null, fn (Builder $q, $key) => $q->byGroupKey($key))
            ->when($filters['brand'] ?? null, fn (Builder $q, $brand) => $q->where('brand', 'like', '%'.$brand.'%'))
            ->when($filters['funding_source'] ?? null, fn (Builder $q, $source) => $q->where('funding_source', $source))
            ->when(isset($filters['price_min']), fn (Builder $q) => $q->where('price', '>=', $filters['price_min']))
            ->when(isset($filters['price_max']), fn (Builder $q) => $q->where('price', '<=', $filters['price_max']))
            ->when($filters['purchase_date_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('purchase_date', '>=', $date))
            ->when($filters['purchase_date_to'] ?? null, fn (Builder $q, $date) => $q->whereDate('purchase_date', '<=', $date))
            ->when(filter_var($filters['warranty_expiring'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->warrantyExpiringSoon((int) ($filters['warranty_days'] ?? 30)));
    }
}
