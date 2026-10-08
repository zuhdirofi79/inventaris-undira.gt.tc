<?php

namespace App\Services;

use App\Enums\InventoryCondition;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\DeletionApproval;
use App\Models\InventoryItem;
use App\Models\InventoryMasterConfiguration;
use App\Models\InventoryMutation;
use App\Models\Maintenance;
use App\Models\MaintenanceSchedule;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Support\InventarisCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permanent delete for an inventory configuration and the physical assets
 * created from the same foreign-key combination.
 *
 * inventory_items has no configuration_id. The link is the key set written by
 * InventoryCreationService::createFromConfiguration:
 * room, classification, subclassification, org unit, procurement type,
 * purchase year, and mapped condition. Items later moved out of that room
 * are included via mutations.from_room_id and block deletion when history exists.
 *
 * Inventory numbers already issued are never rewritten. The sequence counter
 * is not decremented, so a deleted number is not reused.
 */
class InventoryMasterConfigurationDeletionService
{
    public function __construct(
        protected InventoryCreationService $creation,
        protected TrashCascadeService $trash,
    ) {}

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, InventoryMasterConfiguration>  $configurations
     */
    public function attachPreviews($configurations): void
    {
        $configs = $configurations->getCollection();
        if ($configs->isEmpty()) {
            return;
        }

        $configs->loadMissing(['purchaseYear', 'inventoryCondition']);

        $items = InventoryItem::withTrashed()
            ->with(['mutations:id,inventory_item_id,from_room_id'])
            ->where(function (Builder $outer) use ($configs) {
                foreach ($configs as $config) {
                    $outer->orWhere(function (Builder $inner) use ($config) {
                        $this->applyMatch($inner, $config);
                    });
                }
            })
            ->get([
                'id',
                'room_id',
                'category_id',
                'sub_category_id',
                'org_unit_id',
                'procurement_type_id',
                'purchase_year',
                'condition',
            ]);

        $blocked = $this->blockedIds($items->pluck('id'));

        foreach ($configs as $config) {
            $matched = $items->filter(fn (InventoryItem $item) => $this->itemMatches($item, $config));
            $config->setAttribute('related_inventory_count', $matched->count());
            $config->setAttribute('related_blocked_count', $matched->whereIn('id', $blocked->all())->count());
        }
    }

    public function delete(InventoryMasterConfiguration $configuration, User $actor): int
    {
        $deleted = DB::transaction(function () use ($configuration, $actor) {
            $locked = InventoryMasterConfiguration::query()
                ->whereKey($configuration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $items = $this->relatedItemsQuery($locked)->lockForUpdate()->get();
            $blocked = $this->blockedIds($items->pluck('id'));

            if ($blocked->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'configuration' => 'Konfigurasi tidak dapat dihapus permanen karena '
                        .$blocked->count()
                        .' inventaris terkait sudah memiliki riwayat transaksi (peminjaman, maintenance, mutasi, atau stock opname). History tersebut tidak dihapus.',
                ]);
            }

            foreach ($items as $item) {
                $this->trash->forceDelete($item, $actor);
            }

            $this->trash->forceDelete($locked, $actor);

            return $items->count();
        });

        InventarisCache::flush();
        DashboardTreeService::flushCache();

        return $deleted;
    }

    /**
     * @return Builder<InventoryItem>
     */
    public function relatedItemsQuery(InventoryMasterConfiguration $configuration): Builder
    {
        $configuration->loadMissing(['purchaseYear', 'inventoryCondition']);

        return InventoryItem::withTrashed()->where(function (Builder $query) use ($configuration) {
            $this->applyMatch($query, $configuration);
        });
    }

    /**
     * @param  Builder<InventoryItem>  $query
     */
    protected function applyMatch(Builder $query, InventoryMasterConfiguration $configuration): void
    {
        $year = (int) ($configuration->purchaseYear?->year ?? 0);
        $condition = $this->mappedCondition($configuration);

        if ($year < 1 || ! $condition) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where('category_id', $configuration->category_id)
            ->where('sub_category_id', $configuration->sub_category_id)
            ->where('org_unit_id', $configuration->org_unit_id)
            ->where('procurement_type_id', $configuration->procurement_type_id)
            ->where('purchase_year', $year)
            ->where('condition', $condition->value)
            ->where(function (Builder $location) use ($configuration) {
                $location->where('room_id', $configuration->room_id)
                    ->orWhereHas('mutations', function (Builder $mutations) use ($configuration) {
                        $mutations->where('from_room_id', $configuration->room_id);
                    });
            });
    }

    protected function itemMatches(InventoryItem $item, InventoryMasterConfiguration $configuration): bool
    {
        $year = (int) ($configuration->purchaseYear?->year ?? 0);
        $condition = $this->mappedCondition($configuration);
        if ($year < 1 || ! $condition) {
            return false;
        }

        if ((int) $item->category_id !== (int) $configuration->category_id
            || (int) $item->sub_category_id !== (int) $configuration->sub_category_id
            || (int) $item->org_unit_id !== (int) $configuration->org_unit_id
            || (int) $item->procurement_type_id !== (int) $configuration->procurement_type_id
            || (int) $item->purchase_year !== $year) {
            return false;
        }

        $itemCondition = $item->condition instanceof InventoryCondition
            ? $item->condition
            : InventoryCondition::tryFrom((string) $item->condition);

        if ($itemCondition !== $condition) {
            return false;
        }

        if ((int) $item->room_id === (int) $configuration->room_id) {
            return true;
        }

        $mutations = $item->relationLoaded('mutations')
            ? $item->mutations
            : $item->mutations()->get(['id', 'inventory_item_id', 'from_room_id']);

        return $mutations->contains(
            fn (InventoryMutation $mutation) => (int) $mutation->from_room_id === (int) $configuration->room_id
        );
    }

    protected function mappedCondition(InventoryMasterConfiguration $configuration): ?InventoryCondition
    {
        $master = $configuration->inventoryCondition;
        if (! $master) {
            return null;
        }

        return $this->creation->mapCondition($master);
    }

    /**
     * @param  Collection<int, int|string>  $ids
     * @return Collection<int, int>
     */
    protected function blockedIds(Collection $ids): Collection
    {
        $idList = $ids->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values();
        if ($idList->isEmpty()) {
            return collect();
        }

        $blocked = collect();
        $values = $idList->all();

        $blocked = $blocked->merge(Borrowing::withTrashed()->whereIn('inventory_item_id', $values)->pluck('inventory_item_id'));
        $blocked = $blocked->merge(BorrowingItem::query()->whereIn('inventory_item_id', $values)->pluck('inventory_item_id'));
        $blocked = $blocked->merge(Maintenance::withTrashed()->whereIn('inventory_item_id', $values)->pluck('inventory_item_id'));
        $blocked = $blocked->merge(MaintenanceSchedule::query()->whereIn('inventory_item_id', $values)->pluck('inventory_item_id'));
        $blocked = $blocked->merge(InventoryMutation::query()->whereIn('inventory_item_id', $values)->pluck('inventory_item_id'));
        $blocked = $blocked->merge(StockOpnameItem::query()->whereIn('inventory_item_id', $values)->pluck('inventory_item_id'));
        $blocked = $blocked->merge(DeletionApproval::query()->whereIn('inventory_item_id', $values)->pluck('inventory_item_id'));

        return $blocked->map(fn ($id) => (int) $id)->unique()->values();
    }
}
