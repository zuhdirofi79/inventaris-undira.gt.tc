<?php

namespace App\Repositories;

use App\Enums\InventoryStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InventoryRepository
{
    /**
     * @param  array{status?:string|null,campus_id?:int,building_id?:int,floor_id?:int,room_id?:int,category_id?:int,sub_category_id?:int}  $filters
     * @return Builder<\App\Models\InventoryItem>
     */
    public function baseQuery(array $filters = []): Builder
    {
        $query = InventoryItem::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['campus_id'])) {
            $query->forCampus((int) $filters['campus_id']);
        }
        if (! empty($filters['building_id'])) {
            $query->forBuilding((int) $filters['building_id']);
        }
        if (! empty($filters['floor_id'])) {
            $query->forFloor((int) $filters['floor_id']);
        }
        if (! empty($filters['room_id'])) {
            $query->inRoom((int) $filters['room_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (! empty($filters['sub_category_id'])) {
            $query->where('sub_category_id', (int) $filters['sub_category_id']);
        }

        return $query;
    }

    /**
     * @return Collection<int, Campus>
     */
    public function activeCampuses(): Collection
    {
        return Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @return Collection<int, Building>
     */
    public function buildingsForCampus(int $campusId): Collection
    {
        return Building::query()
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @return Collection<int, Floor>
     */
    public function floorsForBuilding(int $buildingId): Collection
    {
        return Floor::query()
            ->where('building_id', $buildingId)
            ->where('is_active', true)
            ->orderBy('floor_number')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'floor_number']);
    }

    /**
     * @return Collection<int, Room>
     */
    public function roomsForFloor(int $floorId): Collection
    {
        return Room::query()
            ->where('floor_id', $floorId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * Categories that have inventory in a room (optionally status-filtered).
     *
     * @return Collection<int, Category>
     */
    public function categoriesForRoom(int $roomId, ?string $status = null): Collection
    {
        $ids = $this->baseQuery(array_filter(['room_id' => $roomId, 'status' => $status]))
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id');

        return Category::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * Categories inside the selected location. A room keeps the previous list.
     * Campus, building, or floor can be used without a room.
     *
     * @param  array{status?:string|null,campus_id?:int,building_id?:int,floor_id?:int,room_id?:int}  $filters
     * @return Collection<int, Category>
     */
    public function categoriesForFilters(array $filters): Collection
    {
        if (! empty($filters['room_id'])) {
            return $this->categoriesForRoom((int) $filters['room_id'], $filters['status'] ?? null);
        }

        $scope = array_filter([
            'campus_id' => $filters['campus_id'] ?? null,
            'building_id' => $filters['building_id'] ?? null,
            'floor_id' => $filters['floor_id'] ?? null,
            'status' => $filters['status'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (! isset($scope['campus_id']) && ! isset($scope['building_id']) && ! isset($scope['floor_id'])) {
            return collect();
        }

        $ids = $this->baseQuery($scope)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id');

        return Category::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @return Collection<int, SubCategory>
     */
    public function subCategoriesForRoomCategory(int $roomId, int $categoryId, ?string $status = null): Collection
    {
        $ids = $this->baseQuery(array_filter([
            'room_id' => $roomId,
            'category_id' => $categoryId,
            'status' => $status,
        ]))
            ->whereNotNull('sub_category_id')
            ->distinct()
            ->pluck('sub_category_id');

        return SubCategory::query()
            ->where('category_id', $categoryId)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @param  array{status?:string|null,campus_id?:int,building_id?:int,floor_id?:int,room_id?:int,category_id?:int}  $filters
     * @return Collection<int, SubCategory>
     */
    public function subCategoriesForFilters(array $filters): Collection
    {
        $categoryId = (int) ($filters['category_id'] ?? 0);
        if ($categoryId < 1) {
            return collect();
        }

        if (! empty($filters['room_id'])) {
            return $this->subCategoriesForRoomCategory(
                (int) $filters['room_id'],
                $categoryId,
                $filters['status'] ?? null
            );
        }

        $scope = array_filter([
            'campus_id' => $filters['campus_id'] ?? null,
            'building_id' => $filters['building_id'] ?? null,
            'floor_id' => $filters['floor_id'] ?? null,
            'category_id' => $categoryId,
            'status' => $filters['status'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (! isset($scope['campus_id']) && ! isset($scope['building_id']) && ! isset($scope['floor_id'])) {
            return collect();
        }

        $ids = $this->baseQuery($scope)
            ->whereNotNull('sub_category_id')
            ->distinct()
            ->pluck('sub_category_id');

        return SubCategory::query()
            ->where('category_id', $categoryId)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @param  array{status?:string|null,campus_id?:int,building_id?:int,floor_id?:int,room_id?:int,category_id?:int,sub_category_id?:int}  $filters
     * @return Collection<int, InventoryItem>
     */
    public function listForLocation(array $filters, ?string $q = null, int $limit = 40): Collection
    {
        $query = $this->selectRows($this->baseQuery($filters));

        if ($q !== null && $q !== '') {
            $this->applyKeyword($query, $q);
            $this->preferInventoryNumber($query, $q);
        }

        return $query->orderBy('inventory_number')->limit($limit)->get();
    }

    /**
     * Quick search — never loads all rows.
     *
     * @param  array{status?:string|null,campus_id?:int,building_id?:int,floor_id?:int,room_id?:int,category_id?:int,sub_category_id?:int}  $filters
     * @return Collection<int, InventoryItem>
     */
    public function search(string $q, array $filters = [], int $limit = 30): Collection
    {
        $query = $this->selectRows($this->baseQuery($filters));
        $this->applyKeyword($query, $q);
        $this->preferInventoryNumber($query, $q);

        return $query->orderBy('inventory_number')->limit($limit)->get();
    }

    /**
     * @param  Builder<InventoryItem>  $query
     * @return Builder<InventoryItem>
     */
    protected function selectRows(Builder $query): Builder
    {
        return $query
            ->with([
                'room:id,code,name,floor_id',
                'room.floor:id,name,building_id',
                'room.floor.building:id,name,campus_id',
                'room.floor.building.campus:id,name',
                'category:id,code,name',
                'subCategory:id,code,name',
                'pic:id,name',
            ])
            ->select([
                'id', 'inventory_number', 'name', 'status', 'condition', 'room_id',
                'category_id', 'sub_category_id', 'responsible_person', 'pic_id', 'barcode', 'serial_number',
            ]);
    }

    /**
     * @param  Builder<InventoryItem>  $query
     */
    protected function applyKeyword(Builder $query, string $q): void
    {
        $query->where(function (Builder $builder) use ($q) {
            $builder->where('inventory_number', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('barcode', 'like', "%{$q}%")
                ->orWhere('serial_number', 'like', "%{$q}%")
                ->orWhereHas('pic', fn (Builder $pic) => $pic->where('name', 'like', "%{$q}%"));
        });
    }

    /**
     * @param  Builder<InventoryItem>  $query
     */
    protected function preferInventoryNumber(Builder $query, string $q): void
    {
        $query->orderByRaw(
            'CASE WHEN inventory_number = ? THEN 0 WHEN inventory_number LIKE ? THEN 1 ELSE 2 END',
            [$q, $q.'%']
        );
    }

    public function findByCode(string $code): ?InventoryItem
    {
        return InventoryItem::query()
            ->with($this->detailRelations())
            ->where(function (Builder $query) use ($code) {
                $query->where('inventory_number', $code)
                    ->orWhere('barcode', $code)
                    ->orWhere('serial_number', $code);
            })
            ->first();
    }

    public function findDetail(int $id): ?InventoryItem
    {
        return InventoryItem::query()
            ->with($this->detailRelations())
            ->find($id);
    }

    /**
     * Single load for Work Order / Schedule context panels (no N+1).
     */
    public function findForMaintenanceContext(int $id): ?InventoryItem
    {
        return InventoryItem::query()
            ->with($this->maintenanceContextRelations())
            ->find($id);
    }

    public function findByCodeForMaintenanceContext(string $code): ?InventoryItem
    {
        return InventoryItem::query()
            ->with($this->maintenanceContextRelations())
            ->where(function (Builder $query) use ($code) {
                $query->where('inventory_number', $code)
                    ->orWhere('barcode', $code)
                    ->orWhere('serial_number', $code);
            })
            ->first();
    }

    /**
     * @return list<string|\Closure>
     */
    public function detailRelations(): array
    {
        return [
            'category:id,code,name',
            'subCategory:id,code,name',
            'room:id,code,name,floor_id',
            'room.floor:id,code,name,building_id',
            'room.floor.building:id,code,name,campus_id',
            'room.floor.building.campus:id,code,name',
            'pic:id,name',
        ];
    }

    /**
     * @return array<int, string|\Closure>
     */
    public function maintenanceContextRelations(): array
    {
        return array_merge($this->detailRelations(), [
            'procurementType:id,code,name',
            'photos' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id')->limit(1),
            'maintenances' => fn ($q) => $q
                ->latest('service_date')
                ->latest('id')
                ->limit(8)
                ->select([
                    'id', 'inventory_item_id', 'maintenance_number', 'maintenance_type',
                    'status', 'technician', 'service_date', 'result',
                ]),
            'maintenanceSchedules' => fn ($q) => $q
                ->where('is_active', true)
                ->orderBy('next_maintenance_date')
                ->limit(5)
                ->select([
                    'id', 'inventory_item_id', 'maintenance_type', 'interval_value',
                    'interval_unit', 'next_maintenance_date', 'responsible_pic', 'is_active',
                ]),
        ]);
    }
}
