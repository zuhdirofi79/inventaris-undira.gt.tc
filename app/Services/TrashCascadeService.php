<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\InventoryMasterConfiguration;
use App\Models\InventoryPhoto;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class TrashCascadeService
{
    public function __construct(
        protected AuditLogService $auditLog,
    ) {}

    public function softDelete(Model $model, User $actor): void
    {
        DB::transaction(function () use ($model, $actor) {
            match (true) {
                $model instanceof Campus => $this->softDeleteCampus($model, $actor),
                $model instanceof Building => $this->softDeleteBuilding($model, $actor),
                $model instanceof Floor => $this->softDeleteFloor($model, $actor),
                $model instanceof Room => $this->softDeleteRoom($model, $actor),
                $model instanceof InventoryItem => $this->softDeleteInventory($model, $actor),
                $model instanceof InventoryMasterConfiguration => $this->performSoftDelete($model, $actor),
                $model instanceof Category => $this->softDeleteCategory($model, $actor),
                $model instanceof SubCategory => $this->softDeleteSubCategory($model, $actor),
                $model instanceof Supplier => $this->performSoftDelete($model, $actor),
                $model instanceof Vendor => $this->performSoftDelete($model, $actor),
                $model instanceof OrgUnit => $this->performSoftDelete($model, $actor),
                $model instanceof ProcurementType => $this->performSoftDelete($model, $actor),
                default => throw new InvalidArgumentException('Tipe data tidak didukung Trash.'),
            };
        });

        DashboardTreeService::flushCache();
    }

    public function restore(Model $model, User $actor): void
    {
        DB::transaction(function () use ($model, $actor) {
            match (true) {
                $model instanceof Campus => $this->restoreCampus($model, $actor),
                $model instanceof Building => $this->restoreBuilding($model, $actor),
                $model instanceof Floor => $this->restoreFloor($model, $actor),
                $model instanceof Room => $this->restoreRoom($model, $actor),
                $model instanceof InventoryItem => $this->performRestore($model, $actor),
                $model instanceof InventoryMasterConfiguration => $this->performRestore($model, $actor),
                $model instanceof Category => $this->restoreCategory($model, $actor),
                $model instanceof SubCategory => $this->performRestore($model, $actor),
                $model instanceof Supplier,
                $model instanceof Vendor,
                $model instanceof OrgUnit,
                $model instanceof ProcurementType => $this->performRestore($model, $actor),
                default => throw new InvalidArgumentException('Tipe data tidak didukung Trash.'),
            };
        });

        DashboardTreeService::flushCache();
    }

    public function forceDelete(Model $model, User $actor): void
    {
        DB::transaction(function () use ($model, $actor) {
            match (true) {
                $model instanceof Campus => $this->forceDeleteCampus($model, $actor),
                $model instanceof Building => $this->forceDeleteBuilding($model, $actor),
                $model instanceof Floor => $this->forceDeleteFloor($model, $actor),
                $model instanceof Room => $this->forceDeleteRoom($model, $actor),
                $model instanceof InventoryItem => $this->forceDeleteInventory($model, $actor),
                $model instanceof InventoryMasterConfiguration => $this->performForceDelete($model, $actor),
                $model instanceof Category => $this->forceDeleteCategory($model, $actor),
                $model instanceof SubCategory => $this->forceDeleteSubCategory($model, $actor),
                $model instanceof Supplier => $this->forceDeleteSupplier($model, $actor),
                $model instanceof Vendor => $this->forceDeleteVendor($model, $actor),
                $model instanceof OrgUnit => $this->forceDeleteOrgUnit($model, $actor),
                $model instanceof ProcurementType => $this->forceDeleteProcurementType($model, $actor),
                default => throw new InvalidArgumentException('Tipe data tidak didukung Trash.'),
            };
        });

        DashboardTreeService::flushCache();
    }

    protected function softDeleteCampus(Campus $campus, User $actor): void
    {
        $campus->buildings()->each(fn (Building $b) => $this->softDeleteBuilding($b, $actor));
        InventoryMasterConfiguration::query()->where('campus_id', $campus->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performSoftDelete($c, $actor));
        $this->performSoftDelete($campus, $actor);
    }

    protected function softDeleteBuilding(Building $building, User $actor): void
    {
        $building->floors()->each(fn (Floor $f) => $this->softDeleteFloor($f, $actor));
        InventoryMasterConfiguration::query()->where('building_id', $building->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performSoftDelete($c, $actor));
        $this->performSoftDelete($building, $actor);
    }

    protected function softDeleteFloor(Floor $floor, User $actor): void
    {
        $floor->rooms()->each(fn (Room $r) => $this->softDeleteRoom($r, $actor));
        InventoryMasterConfiguration::query()->where('floor_id', $floor->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performSoftDelete($c, $actor));
        $this->performSoftDelete($floor, $actor);
    }

    protected function softDeleteRoom(Room $room, User $actor): void
    {
        $room->inventoryItems()->each(fn (InventoryItem $i) => $this->softDeleteInventory($i, $actor));
        InventoryMasterConfiguration::query()->where('room_id', $room->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performSoftDelete($c, $actor));
        $this->performSoftDelete($room, $actor);
    }

    protected function softDeleteInventory(InventoryItem $item, User $actor): void
    {
        $this->performSoftDelete($item, $actor);
    }

    protected function softDeleteCategory(Category $category, User $actor): void
    {
        $category->subCategories()->each(fn (SubCategory $s) => $this->softDeleteSubCategory($s, $actor));
        InventoryMasterConfiguration::query()->where('category_id', $category->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performSoftDelete($c, $actor));
        $this->performSoftDelete($category, $actor);
    }

    protected function softDeleteSubCategory(SubCategory $sub, User $actor): void
    {
        InventoryMasterConfiguration::query()->where('sub_category_id', $sub->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performSoftDelete($c, $actor));
        $this->performSoftDelete($sub, $actor);
    }

    protected function restoreCampus(Campus $campus, User $actor): void
    {
        $this->performRestore($campus, $actor);
        Building::onlyTrashed()->where('campus_id', $campus->id)
            ->each(fn (Building $b) => $this->restoreBuilding($b, $actor));
        InventoryMasterConfiguration::onlyTrashed()->where('campus_id', $campus->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performRestore($c, $actor));
    }

    protected function restoreBuilding(Building $building, User $actor): void
    {
        $this->performRestore($building, $actor);
        Floor::onlyTrashed()->where('building_id', $building->id)
            ->each(fn (Floor $f) => $this->restoreFloor($f, $actor));
        InventoryMasterConfiguration::onlyTrashed()->where('building_id', $building->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performRestore($c, $actor));
    }

    protected function restoreFloor(Floor $floor, User $actor): void
    {
        $this->performRestore($floor, $actor);
        Room::onlyTrashed()->where('floor_id', $floor->id)
            ->each(fn (Room $r) => $this->restoreRoom($r, $actor));
        InventoryMasterConfiguration::onlyTrashed()->where('floor_id', $floor->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performRestore($c, $actor));
    }

    protected function restoreRoom(Room $room, User $actor): void
    {
        $this->performRestore($room, $actor);
        InventoryItem::onlyTrashed()->where('room_id', $room->id)
            ->each(fn (InventoryItem $i) => $this->performRestore($i, $actor));
        InventoryMasterConfiguration::onlyTrashed()->where('room_id', $room->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performRestore($c, $actor));
    }

    protected function restoreCategory(Category $category, User $actor): void
    {
        $this->performRestore($category, $actor);
        SubCategory::onlyTrashed()->where('category_id', $category->id)
            ->each(fn (SubCategory $s) => $this->performRestore($s, $actor));
        InventoryMasterConfiguration::onlyTrashed()->where('category_id', $category->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performRestore($c, $actor));
    }

    protected function forceDeleteCampus(Campus $campus, User $actor): void
    {
        Building::withTrashed()->where('campus_id', $campus->id)
            ->each(fn (Building $b) => $this->forceDeleteBuilding($b, $actor));
        InventoryMasterConfiguration::withTrashed()->where('campus_id', $campus->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performForceDelete($c, $actor));
        $this->performForceDelete($campus, $actor);
    }

    protected function forceDeleteBuilding(Building $building, User $actor): void
    {
        Floor::withTrashed()->where('building_id', $building->id)
            ->each(fn (Floor $f) => $this->forceDeleteFloor($f, $actor));
        InventoryMasterConfiguration::withTrashed()->where('building_id', $building->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performForceDelete($c, $actor));
        $this->performForceDelete($building, $actor);
    }

    protected function forceDeleteFloor(Floor $floor, User $actor): void
    {
        Room::withTrashed()->where('floor_id', $floor->id)
            ->each(fn (Room $r) => $this->forceDeleteRoom($r, $actor));
        InventoryMasterConfiguration::withTrashed()->where('floor_id', $floor->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performForceDelete($c, $actor));
        $this->performForceDelete($floor, $actor);
    }

    protected function forceDeleteRoom(Room $room, User $actor): void
    {
        InventoryItem::withTrashed()->where('room_id', $room->id)
            ->each(fn (InventoryItem $i) => $this->forceDeleteInventory($i, $actor));
        InventoryMasterConfiguration::withTrashed()->where('room_id', $room->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performForceDelete($c, $actor));
        $this->performForceDelete($room, $actor);
    }

    protected function forceDeleteInventory(InventoryItem $item, User $actor): void
    {
        $this->purgeInventoryAssets($item);
        $this->performForceDelete($item, $actor);
    }

    protected function forceDeleteCategory(Category $category, User $actor): void
    {
        InventoryItem::withTrashed()->where('category_id', $category->id)
            ->each(fn (InventoryItem $i) => $this->forceDeleteInventory($i, $actor));
        InventoryMasterConfiguration::withTrashed()->where('category_id', $category->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performForceDelete($c, $actor));
        SubCategory::withTrashed()->where('category_id', $category->id)
            ->each(fn (SubCategory $s) => $this->forceDeleteSubCategory($s, $actor));
        $this->performForceDelete($category, $actor);
    }

    protected function forceDeleteSubCategory(SubCategory $sub, User $actor): void
    {
        InventoryItem::withTrashed()->where('sub_category_id', $sub->id)
            ->each(fn (InventoryItem $i) => $this->forceDeleteInventory($i, $actor));
        InventoryMasterConfiguration::withTrashed()->where('sub_category_id', $sub->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performForceDelete($c, $actor));
        $this->performForceDelete($sub, $actor);
    }

    protected function forceDeleteSupplier(Supplier $supplier, User $actor): void
    {
        InventoryItem::withTrashed()->where('supplier_id', $supplier->id)
            ->update(['supplier_id' => null]);
        $this->performForceDelete($supplier, $actor);
    }

    protected function forceDeleteVendor(Vendor $vendor, User $actor): void
    {
        InventoryItem::withTrashed()->where('vendor_id', $vendor->id)
            ->update(['vendor_id' => null]);
        $this->performForceDelete($vendor, $actor);
    }

    protected function forceDeleteOrgUnit(OrgUnit $orgUnit, User $actor): void
    {
        InventoryItem::withTrashed()->where('org_unit_id', $orgUnit->id)
            ->update(['org_unit_id' => null]);
        InventoryMasterConfiguration::withTrashed()->where('org_unit_id', $orgUnit->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performForceDelete($c, $actor));
        $this->performForceDelete($orgUnit, $actor);
    }

    protected function forceDeleteProcurementType(ProcurementType $type, User $actor): void
    {
        InventoryItem::withTrashed()->where('procurement_type_id', $type->id)
            ->update(['procurement_type_id' => null]);
        InventoryMasterConfiguration::withTrashed()->where('procurement_type_id', $type->id)
            ->each(fn (InventoryMasterConfiguration $c) => $this->performForceDelete($c, $actor));
        $this->performForceDelete($type, $actor);
    }

    protected function purgeInventoryAssets(InventoryItem $item): void
    {
        $disk = Storage::disk('public');

        if (filled($item->qr_code_path) && $disk->exists($item->qr_code_path)) {
            $disk->delete($item->qr_code_path);
        }

        $code = $item->barcode ?: $item->inventory_number;
        if (filled($code)) {
            $safeName = str_replace(['/', '\\'], '-', (string) $code);
            $barcodePath = "inventory/barcodes/{$safeName}.png";
            if ($disk->exists($barcodePath)) {
                $disk->delete($barcodePath);
            }
        }

        InventoryPhoto::query()->where('inventory_item_id', $item->id)->each(function (InventoryPhoto $photo) use ($disk) {
            if (filled($photo->path) && $disk->exists($photo->path)) {
                $disk->delete($photo->path);
            }
            $photo->delete();
        });
    }

    protected function performSoftDelete(Model $model, User $actor): void
    {
        if (method_exists($model, 'trashed') && $model->trashed()) {
            return;
        }

        $old = $model->toArray();
        $model->delete();

        if (method_exists($model, 'markTrashedBy')) {
            $model->markTrashedBy($actor);
        }

        $this->auditLog->log($actor, AuditAction::Delete, $model, $old, null, 'Moved to trash');
    }

    protected function performRestore(Model $model, User $actor): void
    {
        if (! method_exists($model, 'trashed') || ! $model->trashed()) {
            return;
        }

        $model->restore();

        if (method_exists($model, 'markRestoredBy')) {
            $model->markRestoredBy($actor);
        }

        $this->auditLog->log($actor, AuditAction::Restore, $model, null, $model->toArray(), 'Restored from trash');
    }

    protected function performForceDelete(Model $model, User $actor): void
    {
        $old = $model->toArray();
        $this->auditLog->log($actor, AuditAction::Delete, $model, $old, null, 'Force deleted permanently');
        $model->forceDelete();
    }
}
