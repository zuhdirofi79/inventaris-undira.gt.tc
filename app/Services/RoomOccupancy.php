<?php

namespace App\Services;

use App\Enums\FacilityRequestStatus;
use App\Models\InventoryItem;
use App\Models\RoomBorrowing;
use App\Support\ScheduleOverlap;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class RoomOccupancy
{
    public function overlaps(int $roomId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): bool
    {
        $query = RoomBorrowing::query()
            ->where('room_id', $roomId)
            ->where('status', FacilityRequestStatus::Disetujui->value);

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return ScheduleOverlap::apply($query, 'starts_at', 'ends_at', $start, $end)->exists();
    }

    /**
     * Inventaris di dalam ruangan tidak tersedia pada jadwal yang berbenturan
     * dengan peminjaman ruangan yang sudah disetujui. Status fisik inventaris
     * tidak diubah.
     *
     * @param  list<int>  $inventoryIds
     */
    public function assertInventoriesAvailable(array $inventoryIds, CarbonInterface $start, CarbonInterface $end): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $inventoryIds))));
        if ($ids === []) {
            return;
        }

        $items = InventoryItem::query()
            ->whereIn('id', $ids)
            ->whereNotNull('room_id')
            ->get(['id', 'inventory_number', 'room_id']);

        foreach ($items as $item) {
            if ($this->overlaps((int) $item->room_id, $start, $end)) {
                throw ValidationException::withMessages([
                    'items' => "Inventaris {$item->inventory_number} tidak tersedia karena ruangan sedang digunakan pada jadwal tersebut.",
                ]);
            }
        }
    }
}
