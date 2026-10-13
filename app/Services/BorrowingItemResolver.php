<?php

namespace App\Services;

use App\Enums\BorrowingItemStatus;
use App\Enums\InventoryStatus;
use App\Models\Borrowing;
use App\Models\InventoryItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BorrowingItemResolver
{
    /**
     * Expand a seed inventory + qty into distinct borrowable inventory IDs
     * from the same subcategory (preferred) or same name group.
     *
     * @param  list<int>  $excludeIds  IDs already in the current cart / transaction
     * @return list<int>
     */
    public function resolveIds(int $seedInventoryItemId, int $qty = 1, array $excludeIds = [], ?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        $qty = max(1, $qty);
        $seed = InventoryItem::query()->find($seedInventoryItemId);

        if (! $seed) {
            throw ValidationException::withMessages([
                'items' => 'Inventaris tidak ditemukan.',
            ]);
        }

        $this->assertBorrowable($seed, 'items', $start, $end);

        if ($qty === 1) {
            if (in_array($seed->id, $excludeIds, true)) {
                throw ValidationException::withMessages([
                    'items' => "Inventaris {$seed->inventory_number} sudah ada di daftar peminjaman.",
                ]);
            }

            return [$seed->id];
        }

        $candidates = $this->availableSiblings($seed, $excludeIds, $start, $end);

        // Ensure seed is first if still available.
        $ordered = $candidates
            ->sortBy(fn (InventoryItem $item) => $item->id === $seed->id ? 0 : 1)
            ->values();

        if ($ordered->count() < $qty) {
            throw ValidationException::withMessages([
                'items' => "Jumlah tersedia hanya {$ordered->count()} unit untuk \"{$seed->name}\". Diminta: {$qty}.",
            ]);
        }

        return $ordered->take($qty)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $excludeIds
     * @return Collection<int, InventoryItem>
     */
    public function availableSiblings(InventoryItem $seed, array $excludeIds = [], ?CarbonInterface $start = null, ?CarbonInterface $end = null): Collection
    {
        $query = InventoryItem::query()
            ->where('status', InventoryStatus::Aktif)
            ->whereNull('deleted_at')
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds));

        if ($seed->sub_category_id) {
            $query->where('sub_category_id', $seed->sub_category_id);
        } else {
            $query->where('name', $seed->name);
        }

        // Prefer same room when possible, but do not require it.
        $items = $query
            ->orderByRaw('CASE WHEN room_id = ? THEN 0 ELSE 1 END', [$seed->room_id])
            ->orderBy('id')
            ->get(['id', 'inventory_number', 'name', 'status', 'sub_category_id', 'room_id', 'category_id']);

        $scheduled = $start instanceof CarbonInterface && $end instanceof CarbonInterface && $end->greaterThan($start);
        if ($scheduled) {
            $states = app(InventoryAvailability::class)->classify(
                $items->pluck('id')->map(fn ($id) => (int) $id)->all(),
                $start,
                $end,
            );

            return $items->filter(function (InventoryItem $item) use ($states) {
                $status = $item->status instanceof InventoryStatus
                    ? $item->status
                    : InventoryStatus::tryFrom((string) $item->status);

                return ($status?->isBorrowable() ?? false)
                    && ($states[(int) $item->id]['state'] ?? 'blocked') === 'available';
            })->values();
        }

        return $items->filter(function (InventoryItem $item) {
            try {
                $this->assertBorrowable($item);

                return true;
            } catch (ValidationException) {
                return false;
            }
        })->values();
    }

    public function availableCount(InventoryItem $seed, array $excludeIds = [], ?CarbonInterface $start = null, ?CarbonInterface $end = null): int
    {
        return $this->availableSiblings($seed, $excludeIds, $start, $end)->count();
    }

    public function assertBorrowable(InventoryItem $item, string $errorKey = 'items', ?CarbonInterface $start = null, ?CarbonInterface $end = null): void
    {
        $status = $item->status instanceof InventoryStatus
            ? $item->status
            : InventoryStatus::tryFrom((string) $item->status);

        if (! $status?->isBorrowable()) {
            throw ValidationException::withMessages([
                $errorKey => sprintf(
                    'Inventaris %s tidak dapat dipinjam (status: %s). Hanya status Aktif yang boleh dipinjam.',
                    $item->inventory_number,
                    $status?->label() ?? 'tidak valid'
                ),
            ]);
        }

        $scheduled = $start instanceof CarbonInterface && $end instanceof CarbonInterface && $end->greaterThan($start);
        if ($scheduled) {
            app(InventoryAvailability::class)->assertSelectable([(int) $item->id], $start, $end, null, 'items');

            return;
        }

        if (Borrowing::inventoryIsBlocked((int) $item->id)) {
            throw ValidationException::withMessages([
                $errorKey => "Inventaris {$item->inventory_number} sedang dipinjam atau menunggu persetujuan.",
            ]);
        }
    }

    /**
     * Normalize request payload into unique inventory item IDs.
     *
     * Supports:
     * - inventory_item_ids[] (BC / explicit list)
     * - items[][inventory_item_id] + optional items[][qty]
     * - inventory_item_id (legacy single)
     *
     * @return list<int>
     */
    public function normalizeRequestItems(array $input): array
    {
        $window = app(InventoryAvailability::class)->completeWindow(
            $input['borrow_date'] ?? null,
            $input['expected_return_date'] ?? null,
        );
        $start = $window['start'] ?? null;
        $end = $window['end'] ?? null;
        $exclude = [];
        $resolved = [];

        if (! empty($input['inventory_item_ids']) && is_array($input['inventory_item_ids'])) {
            foreach ($input['inventory_item_ids'] as $index => $id) {
                $id = (int) $id;
                if ($id < 1 || in_array($id, $resolved, true)) {
                    continue;
                }
                $this->assertBorrowable(
                    InventoryItem::query()->findOrFail($id),
                    "inventory_item_ids.{$index}",
                    $start,
                    $end,
                );
                $resolved[] = $id;
            }

            return $resolved;
        }

        if (! empty($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $seedId = (int) ($row['inventory_item_id'] ?? 0);
                $qty = max(1, (int) ($row['qty'] ?? 1));
                if ($seedId < 1) {
                    throw ValidationException::withMessages([
                        "items.{$index}.inventory_item_id" => 'Barang inventaris wajib dipilih.',
                    ]);
                }

                $ids = $this->resolveIds($seedId, $qty, array_merge($exclude, $resolved), $start, $end);
                foreach ($ids as $id) {
                    $resolved[] = $id;
                }
            }

            return array_values(array_unique($resolved));
        }

        $legacyId = (int) ($input['inventory_item_id'] ?? 0);
        if ($legacyId > 0) {
            $this->assertBorrowable(
                InventoryItem::query()->findOrFail($legacyId),
                'inventory_item_id',
                $start,
                $end,
            );

            return [$legacyId];
        }

        throw ValidationException::withMessages([
            'items' => 'Minimal satu barang inventaris harus ditambahkan.',
        ]);
    }

    /**
     * @param  list<int>  $inventoryItemIds
     * @return list<array{inventory_item_id:int, qty:int, status:string}>
     */
    public function buildItemRows(array $inventoryItemIds): array
    {
        $rows = [];
        foreach (array_unique($inventoryItemIds) as $id) {
            $rows[] = [
                'inventory_item_id' => (int) $id,
                'qty' => 1,
                'status' => BorrowingItemStatus::MenungguPersetujuan->value,
            ];
        }

        return $rows;
    }
}
