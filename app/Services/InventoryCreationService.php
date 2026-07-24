<?php

namespace App\Services;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Category;
use App\Models\InventoryConditionMaster;
use App\Models\InventoryItem;
use App\Models\InventoryMasterConfiguration;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\PurchaseYear;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single entry-point for creating physical inventory assets.
 * Always writes inventory_items.room_id and generates official inventory number + QR.
 */
class InventoryCreationService
{
    public function __construct(
        protected InventoryCodeService $codes,
        protected LocationCodeGeneratorService $locationCodes,
        protected AuditLogService $auditLog,
    ) {}

    /**
     * Upsert master configuration + create physical InventoryItem rows.
     *
     * @param  array<string, mixed>  $master
     * @param  array<string, mixed>  $asset
     * @return Collection<int, InventoryItem>
     */
    public function createFromConfiguration(array $master, array $asset, User $user): Collection
    {
        return DB::transaction(function () use ($master, $asset, $user) {
            $room = Room::query()->with('floor.building.campus')->findOrFail($master['room_id']);
            $campus = $room->floor?->building?->campus;
            if (! $campus) {
                throw new \RuntimeException('Ruangan tidak terhubung ke kampus.');
            }

            $category = Category::query()->findOrFail($master['category_id']);
            $subCategory = SubCategory::query()->findOrFail($master['sub_category_id']);
            $orgUnit = OrgUnit::query()->findOrFail($master['org_unit_id']);
            $procurement = ProcurementType::query()->findOrFail($master['procurement_type_id']);
            $conditionMaster = InventoryConditionMaster::query()->findOrFail($master['inventory_condition_id']);
            $purchaseYear = PurchaseYear::query()->findOrFail($master['purchase_year_id']);

            $locationCode = $this->locationCodes->generate(
                $campus,
                $orgUnit,
                $room,
            );

            $config = InventoryMasterConfiguration::query()->firstOrCreate(
                [
                    'campus_id' => $master['campus_id'],
                    'building_id' => $master['building_id'],
                    'floor_id' => $master['floor_id'],
                    'org_unit_id' => $master['org_unit_id'],
                    'room_id' => $master['room_id'],
                    'procurement_type_id' => $master['procurement_type_id'],
                    'purchase_year_id' => $master['purchase_year_id'],
                    'category_id' => $master['category_id'],
                    'sub_category_id' => $master['sub_category_id'],
                    'inventory_condition_id' => $master['inventory_condition_id'],
                ],
                [
                    'room_function_id' => $master['room_function_id'] ?? null,
                    'generated_location_code' => $locationCode,
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ],
            );

            if (! $config->wasRecentlyCreated) {
                $config->update([
                    'room_function_id' => $master['room_function_id'] ?? $config->room_function_id,
                    'generated_location_code' => $locationCode,
                    'is_active' => true,
                    'updated_by' => $user->id,
                ]);
            }

            $quantity = max(1, (int) ($asset['quantity'] ?? 1));
            $name = trim((string) $asset['name']);
            $groupKey = $this->codes->generateGroupKey($name, $room->id, $category->id);
            $condition = $this->mapCondition($conditionMaster);
            $picId = ! empty($asset['pic_id']) ? (int) $asset['pic_id'] : null;
            $picName = $picId
                ? User::query()->whereKey($picId)->value('name')
                : null;

            $items = collect();
            for ($i = 0; $i < $quantity; $i++) {
                $inventoryNumber = $this->codes->generateInventoryNumber(
                    $campus,
                    $procurement,
                    (int) $purchaseYear->year,
                    $category,
                    $subCategory,
                );

                $item = InventoryItem::query()->create([
                    'name' => $name,
                    'room_id' => $room->id,
                    'category_id' => $category->id,
                    'sub_category_id' => $subCategory->id,
                    'org_unit_id' => $orgUnit->id,
                    'procurement_type_id' => $master['procurement_type_id'],
                    'purchase_year' => $purchaseYear->year,
                    'funding_source' => $asset['funding_source'] ?? null,
                    'brand' => $asset['brand'] ?? null,
                    'type' => $asset['type'] ?? null,
                    'model' => $asset['model'] ?? null,
                    'serial_number' => $asset['serial_number'] ?? null,
                    'specification' => $asset['specification'] ?? null,
                    'price' => array_key_exists('price', $asset) && $asset['price'] !== null && $asset['price'] !== ''
                        ? $asset['price']
                        : null,
                    'notes' => $asset['notes'] ?? null,
                    'pic_id' => $picId,
                    'responsible_person' => $picName,
                    'unit' => $asset['unit'] ?? 'Unit',
                    'condition' => $condition,
                    'status' => InventoryStatus::Aktif,
                    'inventory_number' => $inventoryNumber,
                    'group_key' => $groupKey,
                    'unit_sequence' => $i + 1,
                    'quantity' => 1,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $this->codes->generateAndSaveCodes($item);
                $this->auditLog->log($user, \App\Enums\AuditAction::Create, $item, null, $item->toArray());
                $items->push($item->fresh());
            }

            DashboardTreeService::flushCache();

            return $items;
        });
    }

    public function mapCondition(InventoryConditionMaster $master): InventoryCondition
    {
        $code = strtolower(trim($master->code));
        $name = strtolower(trim($master->name));

        if ($fromCode = InventoryCondition::tryFrom($code)) {
            return $fromCode;
        }

        return match (true) {
            in_array($code, ['baik', 'bagus', 'good'], true) || str_contains($name, 'baik') || str_contains($name, 'bagus') => InventoryCondition::Bagus,
            in_array($code, ['baru', 'new'], true) || str_contains($name, 'baru') => InventoryCondition::Baru,
            in_array($code, ['layak', 'cukup'], true) || str_contains($name, 'layak') || str_contains($name, 'cukup') => InventoryCondition::Cukup,
            str_contains($code, 'ringan') || str_contains($name, 'ringan') => InventoryCondition::RusakRingan,
            in_array($code, ['rusak', 'rusak_berat', 'berat'], true) || str_contains($name, 'rusak') => InventoryCondition::RusakBerat,
            default => InventoryCondition::Bagus,
        };
    }
}
